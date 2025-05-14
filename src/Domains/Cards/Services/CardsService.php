<?php

namespace Domains\Cards\Services;

use Domains\Cards\Models\Card;
use Domains\Shared\Helpers\ListDataHelper;
use Illuminate\Pagination\LengthAwarePaginator;

class CardsService
{
    /**
     * List all cards with filtering.
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $helper = new ListDataHelper(new Card);
        
        // Ensure only user's cards are returned
        $filters['user_id'] = auth()->id();
        
        return $helper->list($filters);
    }

    /**
     * Get a specific card.
     */
    public function get(string $cardId): Card
    {
        return Card::where([
            'id' => $cardId,
            'user_id' => auth()->id()
        ])->with('rewardPrograms')->firstOrFail();
    }

    /**
     * Create a new card.
     */
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

    /**
     * Update an existing card.
     */
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

    /**
     * Delete a card.
     */
    public function destroy(string $cardId): void
    {
        Card::where([
            'id' => $cardId,
            'user_id' => auth()->id()
        ])->delete();
    }
    
    /**
     * Get all invoices for a specific card.
     */
    public function getInvoices(string $cardId): array
    {
        $card = Card::where([
            'id' => $cardId,
            'user_id' => auth()->id()
        ])->firstOrFail();
        
        return $card->invoices()->with('transactions')->get()->toArray();
    }
    
    /**
     * Sync reward programs for a card.
     */
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