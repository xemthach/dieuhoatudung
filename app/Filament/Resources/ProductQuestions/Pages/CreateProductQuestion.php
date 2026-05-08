<?php

namespace App\Filament\Resources\ProductQuestions\Pages;

use App\Filament\Resources\ProductQuestions\ProductQuestionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductQuestion extends CreateRecord
{
    protected static string $resource = ProductQuestionResource::class;
}
