<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreCategoryRequest;
use App\Http\Requests\Finance\UpdateCategoryRequest;
use Domains\Finance\Services\CategoriesService;
use Illuminate\Http\Request;

class CategoriesController extends Controller
{
    protected CategoriesService $categoriesService;

    public function __construct(CategoriesService $categoriesService)
    {
        $this->categoriesService = $categoriesService;
    }

    /**
     * Display a listing of the categories.
     */
    public function index(Request $request)
    {
        $filters = $request->all();
        $categories = $this->categoriesService->list($filters);

        return response()->json($categories);
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(StoreCategoryRequest $request)
    {
        $data = $request->validated();
        $category = $this->categoriesService->create($data);

        return response()->json($category, 201);
    }

    /**
     * Display the specified category.
     */
    public function show(string $categoryId)
    {
        $category = $this->categoriesService->get($categoryId);

        return response()->json($category);
    }

    /**
     * Update the specified category in storage.
     */
    public function update(UpdateCategoryRequest $request, string $categoryId)
    {
        $data = $request->validated();
        $this->categoriesService->update($categoryId, $data);

        return response()->json([
            'message' => 'Categoria atualizada com sucesso',
        ]);
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(string $categoryId)
    {
        $this->categoriesService->destroy($categoryId);

        return response()->json([
            'message' => 'Categoria deletada com sucesso',
        ], 204);
    }

    /**
     * Get transactions for a specific category.
     */
    public function transactions(Request $request, string $categoryId)
    {
        $filters = $request->all();
        $transactions = $this->categoriesService->getTransactions($categoryId, $filters);

        return response()->json($transactions);
    }

    /**
     * Suggest a category based on merchant name.
     */
    public function suggest(Request $request)
    {
        $merchantName = $request->input('merchant_name');
        $categoryId = $this->categoriesService->suggestCategory($merchantName);

        return response()->json([
            'category_id' => $categoryId
        ]);
    }
}