<?php
use App\Models\{Product,ProductCategory,User,ProductDataGovernanceEvent};
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
require dirname(__DIR__,2).'/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(config('database.default')!=='sqlite'||!str_contains((string)config('database.connections.sqlite.database'),'browser-isolated.sqlite')){
    throw new RuntimeException('Requires isolated browser database');
}
$path=storage_path('framework/testing/bulk-technical-fixture.json');
$mode=$argv[1]??'setup';
if($mode==='snapshot'){
    $s=json_decode(file_get_contents($path),true);
    echo json_encode(['total'=>Product::count(), 'rows'=>Product::whereKey($s['ids'])->orderBy('id')->get(['id','technical_capacity_btu','capacity_kw','hp','voltage','specs_json']),
        'events'=>ProductDataGovernanceEvent::where('operator_id',$s['user_id'])->get(['product_ids_json','descriptor_json','action','operator_id','batch_uuid','occurred_at'])]);
    exit;
}
if($mode==='cleanup'){
    $s=json_decode(file_get_contents($path),true);
    Product::whereKey($s['ids'])->forceDelete();User::whereKey($s['user_id'])->delete();unlink($path);echo '{}';exit;
}
$password=bin2hex(random_bytes(16));
$user=User::factory()->create(['email'=>'bulk-technical@example.test','password'=>Hash::make($password),'is_active'=>true]);
$user->assignRole(Role::firstOrCreate(['name'=>'super_admin','guard_name'=>'web']));
$category=ProductCategory::where('slug','dieu-hoa-treo-tuong')->firstOrFail();
$ids=[];
foreach(range(1,30) as $i){
    $p=Product::factory()->create(['name'=>($i<=3?'BulkTechnicalSelected':'BulkTechnicalFiltered').' '.$i,
        'product_category_id'=>$category->id,'technical_capacity_btu'=>18000,'capacity_kw'=>5,'hp'=>2,'inverter'=>true,
        'voltage'=>'220V','is_active'=>true,'specs_json'=>null]);
    $ids[]=$p->id;
}
$s=['email'=>$user->email,'password'=>$password,'user_id'=>$user->id,'ids'=>$ids];
file_put_contents($path,json_encode($s));echo json_encode($s);
