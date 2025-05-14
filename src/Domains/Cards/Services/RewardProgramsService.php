<?php

namespace Domains\Cards\Services;

use Domains\Cards\Models\RewardProgram;
use Domains\Shared\Helpers\ListDataHelper;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

class RewardProgramsService
{
    /**
     * List all reward programs with filtering.
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $helper = new ListDataHelper(new RewardProgram);
        return $helper->list($filters);
    }

    /**
     * Get a specific reward program.
     */
    public function get(string $programId): RewardProgram
    {
        return RewardProgram::findOrFail($programId);
    }

    /**
     * Create a new reward program.
     */
    public function create(array $data): RewardProgram
    {
        // Handle logo upload if provided
        if (isset($data['logo']) && $data['logo']) {
            $data['logo_path'] = $this->uploadLogo($data['logo']);
            unset($data['logo']);
        }
        
        return RewardProgram::create($data);
    }

    /**
     * Update an existing reward program.
     */
    public function update(string $programId, array $data): void
    {
        $program = RewardProgram::findOrFail($programId);
        
        // Handle logo upload if provided
        if (isset($data['logo']) && $data['logo']) {
            // Delete old logo if exists
            if ($program->logo_path) {
                Storage::delete($program->logo_path);
            }
            
            $data['logo_path'] = $this->uploadLogo($data['logo']);
            unset($data['logo']);
        }
        
        $program->update($data);
    }

    /**
     * Delete a reward program.
     */
    public function destroy(string $programId): void
    {
        $program = RewardProgram::findOrFail($programId);
        
        // Delete logo if exists
        if ($program->logo_path) {
            Storage::delete($program->logo_path);
        }
        
        $program->delete();
    }
    
    /**
     * Upload a logo for the reward program.
     */
    private function uploadLogo($logo): string
    {
        $path = $logo->store('reward-programs', 's3');
        return $path;
    }
    
    /**
     * Get all cards associated with a reward program.
     */
    public function getLinkedCards(string $programId): array
    {
        $program = RewardProgram::findOrFail($programId);
        return $program->cards()->get()->toArray();
    }
}