<?php

namespace App\Services\Dealer;

use Illuminate\Database\Eloquent\Builder;

final class DealerLocationQuery
{
    public function apply(Builder $query, float $lat, float $lng, ?int $radius): Builder
    {
        $connection = $query->getConnection();
        if ($connection->getDriverName() === 'sqlite') {
            foreach (['sin', 'cos', 'asin', 'sqrt'] as $function) {
                $connection->getPdo()->sqliteCreateFunction($function,
                    static fn ($v) => $v === null ? null : $function((float) $v), 1, \PDO::SQLITE_DETERMINISTIC);
            }
        }
        $radians = pi() / 180;
        $a = '(sin((latitude * ? - ?) / 2) * sin((latitude * ? - ?) / 2)'
            .' + cos(?) * cos(latitude * ?) * sin((longitude * ? - ?) / 2) * sin((longitude * ? - ?) / 2))';
        $bindings = [$radians, deg2rad($lat), $radians, deg2rad($lat), deg2rad($lat), $radians,
            $radians, deg2rad($lng), $radians, deg2rad($lng)];
        $expression = "12742 * asin(sqrt(case when {$a} < 0 then 0 when {$a} > 1 then 1 else {$a} end))";
        $allBindings = [...$bindings, ...$bindings, ...$bindings];
        $query->whereNotNull('latitude')->whereNotNull('longitude')
            ->selectRaw($expression.' as distance_km', $allBindings);
        if ($radius !== null) {
            $query->whereRaw('('.$expression.') <= ?', [...$allBindings, $radius]);
        }

        return $query->reorder()->orderBy('distance_km')->orderBy('dealers.id');
    }
}
