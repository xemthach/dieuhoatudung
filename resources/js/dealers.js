import 'leaflet/dist/leaflet.css';
import '../css/dealers.css';

let library;
const maps = new WeakMap();
const validPoint = p => p.latitude !== null && p.longitude !== null
    && Number.isFinite(Number(p.latitude)) && Number.isFinite(Number(p.longitude))
    && Math.abs(Number(p.latitude)) <= 90 && Math.abs(Number(p.longitude)) <= 180;

async function refreshMaps() {
    const elements = [...document.querySelectorAll('.dealer-map')].filter(e => e.offsetWidth > 0);
    if (!elements.length) return;
    library ??= import('leaflet');
    const {default: L} = await library;
    for (const element of elements) {
        const serialized = element.dataset.markers || '[]';
        let state = maps.get(element);
        if (!state) {
            const map = L.map(element).setView([16,106],5);
            const markers = L.layerGroup().addTo(map);
            const tile = L.tileLayer(element.dataset.tileUrl, {attribution: element.dataset.attribution,maxZoom:19}).addTo(map);
            tile.on('tileerror', () => {
                const message = element.parentElement.querySelector('.dealer-map-status');
                if (message) message.textContent = 'Chưa tải được nền bản đồ. Bạn vẫn có thể mở liên kết chỉ đường.';
            });
            state = {map,markers,serialized:null};
            maps.set(element,state);
        }
        if (state.serialized === serialized) continue;
        state.serialized = serialized;
        state.markers.clearLayers();
        const points = JSON.parse(serialized).filter(validPoint);
        for (const point of points) {
            const box = document.createElement('div');
            const link = document.createElement('a');
            link.textContent = point.name || 'Vị trí đã chọn';
            if (point.url) link.href = point.url;
            box.append(link);
            const address = document.createElement('p');
            address.textContent = point.address || '';
            box.append(address);
            if (point.rating) {
                const rating=document.createElement('p');rating.textContent=Number(point.rating).toFixed(1)+'/5';box.append(rating);
            }
            if (point.distance_km !== null && point.distance_km !== undefined) {
                const distance=document.createElement('p');distance.textContent=Number(point.distance_km).toFixed(1)+' km';box.append(distance);
            }
            L.circleMarker([Number(point.latitude),Number(point.longitude)],{radius:8,color:'#1d4ed8',fillOpacity:0.8}).bindPopup(box).addTo(state.markers);
        }
        state.map.invalidateSize();
        if (points.length) state.map.fitBounds(L.latLngBounds(points.map(p=>[Number(p.latitude),Number(p.longitude)])),{maxZoom:15,padding:[20,20]});
    }
}

async function directory(root) {
    if(root.dataset.initialized) return;
    root.dataset.initialized='true';
    const form = root.querySelector('form');
    const status = root.querySelector('[data-location-status]');
    const mapWrapper = root.querySelector('[data-directory-map]');
    let position;
    let locationEpoch=0;
    let nearbyEpoch=0;
    const selected = name => form.elements.namedItem(name);
    async function load(name, kind, parent, initial=false, epoch=locationEpoch) {
        if(epoch!==locationEpoch)return false;
        const select=selected(name);
        const previous=initial ? select.dataset.selected : '';
        select.replaceChildren(new Option('Tất cả',''));
        if (kind!=='province' && !parent) return true;
        const url=new URL(root.dataset.locationUrl,window.location.origin);
        url.searchParams.set('scheme',selected('scheme').value);url.searchParams.set('kind',kind);
        if(parent)url.searchParams.set('parent',parent);
        const response=await fetch(url,{headers:{Accept:'application/json'}});
        if(!response.ok)throw new Error('location');
        const rows=await response.json();
        if(epoch!==locationEpoch)return false;
        for(const row of rows)select.add(new Option(row.full_name,row.id));
        select.value=previous || '';
        return true;
    }
    async function cascade(initial=false) {
        const epoch=++locationEpoch;
        const legacy=selected('scheme').value==='legacy';
        root.querySelector('[data-legacy-only]').hidden=!legacy;
        selected('district').disabled=!legacy;
        if(!legacy)selected('district').value='';
        if(!await load('province','province',null,initial,epoch))return;
        if(legacy && !await load('district','district',selected('province').value,initial,epoch))return;
        await load('ward','ward',legacy?selected('district').value:selected('province').value,initial,epoch);
    }
    selected('scheme').addEventListener('change',()=>cascade().catch(()=>{status.textContent='Chưa tải được danh mục địa giới.';}));
    selected('province').addEventListener('change',async()=>{
        try {
            const epoch=++locationEpoch;
            selected('ward').replaceChildren(new Option('Tất cả',''));
            if(selected('scheme').value==='legacy' && !await load('district','district',selected('province').value,false,epoch))return;
            await load('ward','ward',selected('scheme').value==='legacy'?selected('district').value:selected('province').value,false,epoch);
        } catch {status.textContent='Chưa tải được địa giới.';}
    });
    selected('district').addEventListener('change',()=>load('ward','ward',selected('district').value,false,++locationEpoch).catch(()=>{status.textContent='Chưa tải được địa giới.';}));
    root.querySelector('[data-toggle-map]')?.addEventListener('click',e=>{
        mapWrapper.hidden=!mapWrapper.hidden;e.currentTarget.setAttribute('aria-expanded',String(!mapWrapper.hidden));
        refreshMaps();
    });
    const radius=root.querySelector('[data-radius]');
    async function nearby() {
        const epoch=++nearbyEpoch;
        status.textContent='Đang tìm đại lý gần bạn…';
        const body=Object.fromEntries(new FormData(form));
        delete body.sort;
        Object.assign(body,{latitude:position.latitude,longitude:position.longitude,radius:radius.value});
        try {
            const response=await window.csrfFetch(root.dataset.nearbyUrl,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify(body)});
            if(!response.ok)throw new Error('nearby');
            const data=await response.json();
            if(epoch!==nearbyEpoch)return;
            root.querySelector('[data-dealer-results]').innerHTML=data.html;
            root.querySelector('[data-dealer-pagination]').hidden=true;
            root.querySelector('[data-result-count]').textContent=data.count+' đại lý gần bạn';
            mapWrapper.querySelector('.dealer-map').dataset.markers=JSON.stringify(data.markers);
            status.textContent=data.count
                ? (data.radius===null?'Đang sắp xếp gần nhất, không giới hạn bán kính.':'Đại lý trong '+data.radius+' km, gần nhất trước.')
                : 'Không có đại lý phù hợp trong bán kính đã chọn. Bạn có thể chọn khu vực hoặc tăng bán kính.';
            if(selected('sort') && !selected('sort').querySelector('option[value=distance]')) selected('sort').add(new Option('Gần tôi nhất','distance'));
            selected('sort').value='distance';
            refreshMaps();
        } catch {if(epoch===nearbyEpoch)status.textContent='Chưa thể tìm theo vị trí. Bạn có thể chọn Tỉnh/Thành và Phường/Xã.';}
    }
    root.querySelector('[data-nearby]')?.addEventListener('click',()=>{
        if(!navigator.geolocation){status.textContent='Trình duyệt chưa hỗ trợ vị trí. Hãy chọn khu vực.';return;}
        status.textContent='Đang xác định vị trí…';
        navigator.geolocation.getCurrentPosition(p=>{position=p.coords;nearby();},e=>{
            const messages={1:'Bạn đã từ chối vị trí. Hãy chọn khu vực.',2:'Chưa xác định được vị trí. Hãy chọn khu vực.',3:'Hết thời gian lấy vị trí. Hãy chọn khu vực.'};
            status.textContent=messages[e.code]||'Hãy chọn khu vực để tìm đại lý.';
        },{enableHighAccuracy:false,timeout:10000,maximumAge:60000});
    });
    radius?.addEventListener('change',()=>{if(position)nearby();});
    form?.addEventListener('submit',e=>{
        if(position){e.preventDefault();nearby();}
        else if(selected('sort').value==='distance')selected('sort').value='featured';
    });
    // Bind controls before waiting on region requests: early clicks must work.
    try {await cascade(true);} catch {status.textContent='Chưa tải được danh mục địa giới. Bạn có thể thử lại.';}
}

function initialize() {
    document.querySelectorAll('[data-dealer-directory]').forEach(root=>directory(root));
    refreshMaps();
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initialize);else initialize();
let scheduled=false;
new MutationObserver(()=>{
    if(scheduled)return;
    scheduled=true;
    requestAnimationFrame(()=>{scheduled=false;refreshMaps();});
}).observe(document.documentElement,{childList:true,subtree:true,attributes:true,attributeFilter:['data-markers']});
