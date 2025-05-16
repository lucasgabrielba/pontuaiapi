<?php

namespace Domains\Cards\Services;

use Domains\Cards\Models\Card;
use Domains\Shared\Helpers\ListDataHelper;
use Illuminate\Pagination\LengthAwarePaginator;

class CardsService
{
    public function list(array $filters): LengthAwarePaginator
    {
        $helper = new ListDataHelper(new Card);
        $filters['user_id'] = auth()->id();
        
        if (!isset($filters['order'])) {
            $filters['order'] = '-created_at'; 
        }
        
        return $helper->list($filters);
    }

    public function hasCards(): bool
    {
        return Card::where('user_id', auth()->id())->exists();
    }

    public function get(string $cardId): Card
    {
        return Card::where([
            'id' => $cardId,
            'user_id' => auth()->id()
        ])->with('rewardPrograms')->firstOrFail();
    }

    public function switchStatus(string $cardId, bool $isActive): Card
    {
        $card = Card::where([
            'id' => $cardId,
            'user_id' => auth()->id()
        ])->firstOrFail();

        $card->update(['active' => $isActive]);

        return $card;
    }


    public function create(array $data): Card
    {
        // Add user_id to the card data
        $data['user_id'] = auth()->id();

        // Create the card
        $card = Card::create($data);

        // Link reward programs if provided
        if (isset($data['reward_programs']) && is_array($data['reward_programs'])) {
            $this->syncRewardPrograms($card, $data['reward_programs']);
        }

        return $card->load('rewardPrograms');
    }


    public function update(string $cardId, array $data): void
    {
        $card = Card::where([
            'id' => $cardId,
            'user_id' => auth()->id()
        ])->firstOrFail();

        // Extract reward programs if exists
        $rewardPrograms = $data['reward_programs'] ?? null;
        unset($data['reward_programs']);

        // Update card data
        $card->update($data);

        // Update reward programs if provided
        if (is_array($rewardPrograms)) {
            $this->syncRewardPrograms($card, $rewardPrograms);
        }
    }


    public function destroy(string $cardId): void
    {
        Card::where([
            'id' => $cardId,
            'user_id' => auth()->id()
        ])->delete();
    }


    public function getInvoices(string $cardId): array
    {
        $card = Card::where([
            'id' => $cardId,
            'user_id' => auth()->id()
        ])->firstOrFail();
        return $card->invoices()->with('transactions')->get()->toArray();
    }


    private function syncRewardPrograms(Card $card, array $rewardPrograms): void
    {
        $sync = [];

        foreach ($rewardPrograms as $program) {
            $programId = $program['reward_program_id'];
            $pivot = [
                'conversion_rate' => $program['conversion_rate'] ?? 1.0,
                'is_primary' => $program['is_primary'] ?? false,
                'terms' => $program['terms'] ?? null,
            ];

            $sync[$programId] = $pivot;
        }

        $card->rewardPrograms()->sync($sync);
    }
}