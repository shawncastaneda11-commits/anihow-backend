<?php

namespace Database\Seeders;

use App\Models\FaqEntry;
use App\Support\FaqCatalog;
use Illuminate\Database\Seeder;

class FaqEntrySeeder extends Seeder
{
    public function run(): void
    {
        foreach (FaqCatalog::intents() as $index => $intent) {
            FaqEntry::query()->updateOrCreate(
                [
                    'farm_id' => null,
                    'intent_key' => $intent['id'],
                ],
                [
                    'roles' => $intent['roles'],
                    'label' => $intent['label'],
                    'label_fil' => $intent['label_fil'],
                    'keywords' => $intent['keywords'],
                    'answer' => $intent['answer'],
                    'answer_fil' => $intent['answer_fil'],
                    'sort_order' => $index,
                    'is_active' => true,
                ],
            );
        }
    }
}
