<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            if (! Schema::hasColumn('faqs', 'normalized_search_text')) {
                $table->text('normalized_search_text')->nullable()->after('group');
            }
        });

        DB::table('faqs')
            ->select(['id', 'group', 'question', 'answer'])
            ->orderBy('id')
            ->chunkById(100, function ($faqs): void {
                foreach ($faqs as $faq) {
                    $text = trim(implode(' ', array_filter([
                        $faq->group,
                        $faq->question,
                        strip_tags((string) $faq->answer),
                    ])));

                    DB::table('faqs')
                        ->where('id', $faq->id)
                        ->update(['normalized_search_text' => Str::ascii(Str::lower($text))]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            if (Schema::hasColumn('faqs', 'normalized_search_text')) {
                $table->dropColumn('normalized_search_text');
            }
        });
    }
};
