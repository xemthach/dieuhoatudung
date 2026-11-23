import {execFileSync} from 'node:child_process';
import {test,expect,Page} from '@playwright/test';

const fixture=(mode:string)=>JSON.parse(execFileSync('php',['tests/browser/dealer-fixture.php',mode],{encoding:'utf8'}));
let state:any;
test.use({actionTimeout:15000});
const pixel=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+yHhkAAAAASUVORK5CYII=','base64');
test.beforeAll(()=>{state=fixture('setup')});
test.afterAll(()=>{fixture('cleanup')});
test.beforeEach(async({context})=>{
    await context.route('https://tile.openstreetmap.org/**',route=>route.fulfill({status:200,contentType:'image/png',body:pixel}));
});
async function login(page:Page) {
    await page.goto('/admin/login');
    await page.locator('[id="form.email"]').fill(state.email);
    await page.locator('[id="form.password"]').fill(state.password);
    await page.locator('button[type=submit]').click();
    await expect(page).toHaveURL(/\/admin\/?$/);
}
function errors(page:Page) {
    const items:string[]=[];
    page.on('console',m=>{if(m.type()==='error')items.push('console '+m.text())});
    page.on('pageerror',e=>items.push('page '+e.message));
    page.on('requestfailed',r=>items.push('request '+r.url()));
    page.on('response',r=>{if(r.status()>=500||(r.url().includes('livewire')&&r.status()>=400))items.push('HTTP '+r.status()+' '+r.url())});
    return items;
}
async function choose(page:Page,id:string,label:string) {
    await page.locator('[id="form.'+id+'"]').click();
    await page.getByRole('option',{name:label,exact:true}).click();
    await page.keyboard.press('Escape');
}
test('Admin creates dealer with current and legacy address, map and relationships; guest review is moderated directly',async({page,context})=>{
    test.setTimeout(240000);
    const issues=errors(page);
    await login(page);
    await page.goto('/admin/dealers/create');
    await page.locator('[id="form.name"]').fill('Dealer Browser Created');
    await page.locator('[id="form.phone"]').fill('0901234567');
    await page.locator('[id="form.is_active"]').click();
    await page.getByRole('tab',{name:'Địa chỉ',exact:true}).click();
    await page.locator('[id="form.address_line"]').fill('456 Browser Street');
    await choose(page,'province_id','Tỉnh 01');
    await choose(page,'ward_id','Xã 00001');
    await choose(page,'legacy_province_id','Tỉnh 01');
    await choose(page,'legacy_district_id','Huyện 001');
    await choose(page,'legacy_ward_id','Xã 00001');
    await page.getByRole('tab',{name:'Bản đồ',exact:true}).click();
    await page.locator('[id="form.openstreetmap_url"]').fill('https://www.openstreetmap.org/#map=15/10/106');
    await page.getByRole('button',{name:'Lấy tọa độ OSM',exact:true}).click();
    await expect(page.locator('[id="form.latitude"]')).toHaveValue('10');
    await expect(page.locator('.dealer-map.leaflet-container').first()).toBeVisible();
    await expect(page.locator('.dealer-map path.leaflet-interactive').first()).toBeVisible();
    await page.getByRole('tab',{name:'Thương hiệu / Dòng sản phẩm',exact:true}).click();
    await choose(page,'brands','Dealer Test Brand');
    await choose(page,'categories','Điều hòa treo tường');
    await page.getByRole('tab',{name:'Dịch vụ',exact:true}).click();
    await choose(page,'services','Lắp đặt Dealer');
    await page.getByRole('button',{name:/^Tạo$|^Create$/}).first().click();
    await expect(page).not.toHaveURL(/create$/);
    await expect.poll(()=>fixture('snapshot').dealers.some((d:any)=>d.name==='Dealer Browser Created')).toBe(true);
    const dealer=fixture('snapshot').dealers.find((d:any)=>d.name==='Dealer Browser Created');
    expect(dealer.ward_id).toBe(state.ward);
    expect(dealer.legacy_ward_id).toBe(state.legacy_ward);
    expect(dealer.brands).toHaveLength(1);expect(dealer.categories).toHaveLength(1);expect(dealer.services).toHaveLength(1);
    await page.goto('/admin/dealers/'+dealer.id+'/edit');
    await page.getByRole('tab',{name:'Địa chỉ',exact:true}).click();
    await expect(page.locator('[id="form.ward_id"]').locator('..')).toContainText('Xã 00001');
    const guest=await context.browser()!.newContext({baseURL:'http://127.0.0.1:8098'});
    await guest.route('https://tile.openstreetmap.org/**',r=>r.fulfill({status:200,contentType:'image/png',body:pixel}));
    const publicPage=await guest.newPage();const guestIssues=errors(publicPage);
    await publicPage.goto('/dai-ly?province='+state.province+'&ward='+state.ward+'&brand='+state.brand+'&category='+state.category+'&service='+state.service);
    await expect(publicPage.locator('[data-dealer-results]')).toContainText('Dealer Browser Created');
    await publicPage.getByRole('link',{name:'Xem chi tiết',exact:true}).click();
    await expect(publicPage.locator('body')).not.toContainText('seo_title ?:');
    await expect(publicPage.locator('body')).not.toContainText('seo-description=');
    await expect(publicPage.locator('head title')).not.toBeEmpty();
    await expect(publicPage.locator('head link[rel=canonical]')).toHaveCount(1);
    await expect(publicPage.locator('head meta[name=robots]')).toHaveAttribute('content', /index|noindex/);
    await expect(publicPage.locator('[data-brand-style]')).toBeVisible();
    await expect(publicPage.locator('[data-product-line-style]')).toBeVisible();
    await expect(publicPage.locator('[data-service-style]')).toBeVisible();
    await expect(publicPage.locator('.leaflet-container')).toBeVisible();
    for(const width of [1440,1024,768,390]) {
        await publicPage.setViewportSize({width,height:844});
        expect(await publicPage.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth)).toBe(true);
    }
    await publicPage.locator('[name=customer_name]').fill('Guest Browser');
    await publicPage.getByRole('radio',{name:'5 sao',exact:true}).check();
    await publicPage.locator('[name=title]').fill('Service browser review');
    await publicPage.locator('[name=content]').fill('Dịch vụ lắp đặt chu đáo và rất chuyên nghiệp.');
    await publicPage.getByRole('button',{name:'Gửi đánh giá',exact:true}).click();
    await expect(publicPage.getByRole('status').filter({hasText:'đang chờ duyệt'})).toBeVisible();
    await expect(publicPage.getByText('Dịch vụ lắp đặt chu đáo và rất chuyên nghiệp.',{exact:true})).toHaveCount(0);
    expect(fixture('snapshot').reviews[0].status).toBe('pending');
    await page.goto('/admin/dealer-reviews');
    await page.getByRole('button',{name:'Duyệt',exact:true}).first().click();
    await expect.poll(()=>fixture('snapshot').reviews[0].status).toBe('approved');
    expect(fixture('snapshot').reviews[0].approved_by).toBe(state.user_id);
    await publicPage.reload();
    await expect(publicPage.getByText('Dịch vụ lắp đặt chu đáo và rất chuyên nghiệp.',{exact:true})).toBeVisible();
    await expect(publicPage.getByText('5,0/5 · 1 đánh giá',{exact:true})).toBeVisible();
    expect(issues).toEqual([]);expect(guestIssues).toEqual([]);
    await guest.close();
});

test('nearby asks only after click, posts private coordinates, respects radius and combines filters',async({page,context})=>{
    const issues=errors(page);const requests:string[]=[];
    await context.grantPermissions(['geolocation']);
    await context.setGeolocation({latitude:10.0012345,longitude:106.0012345});
    await page.addInitScript(()=>{
        (window as any).geoCalls=0;
        const original=navigator.geolocation.getCurrentPosition.bind(navigator.geolocation);
        navigator.geolocation.getCurrentPosition=(...args:any[])=>{(window as any).geoCalls++;return original(...args as Parameters<typeof original>)};
    });
    page.on('request',r=>requests.push(r.url()));
    await page.goto('/dai-ly');
    await expect(page.locator('[name=province] option')).toHaveCount(3);
    expect(await page.evaluate(()=>(window as any).geoCalls)).toBe(0);
    await page.getByRole('button',{name:'Tìm đại lý gần tôi',exact:true}).click();
    await expect(page.locator('[data-result-count]')).toContainText('đại lý gần bạn');
    await expect(page.locator('[data-dealer-results]')).toContainText('Dealer Nearby');
    await expect(page.locator('[data-dealer-results]')).not.toContainText('Dealer Far');
    await expect(page.locator('[data-dealer-results]')).not.toContainText('Dealer No Coordinates');
    await expect(page.locator('[data-dealer-results]')).toContainText('km');
    await expect(page).toHaveURL(/\/dai-ly$/);
    expect(requests.some(u=>u.includes('10.0012345')||u.includes('106.0012345'))).toBe(false);
    await page.locator('[data-radius]').selectOption('all');
    await expect(page.locator('[data-dealer-results]')).toContainText('Dealer Far');
    await page.locator('[name=brand]').selectOption(String(state.brand));
    await page.getByRole('button',{name:'Tìm đại lý',exact:true}).click();
    await expect(page.locator('[data-dealer-results]')).toContainText('Dealer Browser Created');
    await expect(page.locator('[data-dealer-results]')).not.toContainText('Dealer Nearby');
    await page.getByRole('button',{name:'Bản đồ / Danh sách',exact:true}).click();
    await expect(page.locator('.leaflet-container')).toBeVisible();
    expect(issues).toEqual([]);
});

test('homepage shows featured fallback then uses explicit near-me request',async({page,context})=>{
    test.setTimeout(120000);
    fixture('homepage');
    const issues=errors(page);const requests:string[]=[];
    await context.grantPermissions(['geolocation']);
    await context.setGeolocation({latitude:10.0012345,longitude:106.0012345});
    await page.addInitScript(()=>{
        (window as any).geoCalls=0;
        const original=navigator.geolocation.getCurrentPosition.bind(navigator.geolocation);
        navigator.geolocation.getCurrentPosition=(...args:any[])=>{(window as any).geoCalls++;return original(...args as Parameters<typeof original>)};
    });
    page.on('request',request=>requests.push(request.url()));
    await page.goto('/');
    await expect(page.locator('[data-homepage-dealers]')).toBeVisible();
    const sectionOrder = await page.locator('[data-homepage-section]').evaluateAll(elements => elements.map(element => element.getAttribute('data-homepage-section')));
    expect(sectionOrder.indexOf('featured_products')).toBeLessThan(sectionOrder.indexOf('dealers'));
    expect(sectionOrder.indexOf('dealers')).toBeLessThan(sectionOrder.indexOf('brands'));
    expect(sectionOrder.filter(section => section === 'dealers')).toHaveLength(1);
    await expect(page.locator('[data-dealer-results]')).toContainText('Dealer Featured Homepage');
    expect(await page.evaluate(()=>(window as any).geoCalls)).toBe(0);
    await page.getByRole('button',{name:/Tìm đại lý gần tôi/}).click();
    await expect(page.locator('[data-dealer-results]')).toContainText('Dealer Nearby');
    await expect(page.locator('[data-dealer-results]')).not.toContainText('Dealer Featured Homepage');
    await expect(page).toHaveURL('/');
    expect(requests.some(url=>url.includes('10.0012345')||url.includes('106.0012345'))).toBe(false);
    expect(issues).toEqual([]);
});

test('homepage region selection filters to selected province',async({page})=>{
    fixture('homepage');
    await page.goto('/');
    const province=page.locator('[data-home-dealers] [name=province]');
    await province.selectOption(String(state.province2));
    await page.locator('[data-home-dealers] form button[type=submit]').click();
    await expect(page).toHaveURL(new RegExp('province='+state.province2));
    await expect(page.locator('[data-dealer-results]')).toContainText('Dealer Regional Homepage');
    await expect(page.locator('[data-dealer-results]')).not.toContainText('Dealer Featured Homepage');
});

for(const [code,message] of [[1,'từ chối'],[2,'Chưa xác định'],[3,'Hết thời gian']] as const) {
    test('mobile geolocation fallback '+code,async({page})=>{
        const issues=errors(page);
        await page.setViewportSize({width:390,height:844});
        await page.addInitScript(code=>{
            navigator.geolocation.getCurrentPosition=(_ok,fail)=>fail?.({code,message:'mock'} as GeolocationPositionError);
        },code);
        await page.goto('/dai-ly');
        await page.getByRole('button',{name:'Tìm đại lý gần tôi',exact:true}).click();
        await expect(page.locator('[data-location-status]')).toContainText(message);
        await expect(page.locator('[name=province] option')).toHaveCount(3);
        await page.locator('[name=province]').selectOption(String(state.province));
        await expect(page.locator('[name=ward] option')).toHaveCount(2);
        await page.locator('[name=ward]').selectOption(String(state.ward));
        await page.getByRole('button',{name:'Tìm đại lý',exact:true}).click();
        await expect(page.locator('[data-dealer-results]')).toContainText('Dealer No Coordinates');
        const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth);
        expect(overflow).toBe(false);
        expect(issues).toEqual([]);
    });
}
