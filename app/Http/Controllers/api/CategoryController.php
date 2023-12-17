<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\VCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    public function indexCategories($vcardId)
    {
        try {
            // Find the vCard
            $vcard = VCard::find($vcardId);

            // Check if the vCard exists
            if (!$vcard) {
                return response()->json(['error' => 'VCard not found'], 404);
            }

            // Fetch categories for the vCard
            $categories = $vcard->categories;

            return response()->json(['categories' => $categories], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch categories', 'exception' => $e->getMessage()], 500);
        }
    }

    public function updateCategory(Request $request, $vcardId)
    {
        try {
            // Find the vCard
            $vcard = VCard::find($vcardId);

            // Check if the vCard exists
            if (!$vcard) {
                return response()->json(['error' => 'VCard not found'], 404);
            }

            // Validate the request data
            $validator = Validator::make($request->all(), [
                'categories' => 'required|array',
                'categories.*.name' => 'required|string',
                'categories.*.type' => 'required|in:D,C',
                // Add any other validation rules for category fields
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            // Update categories for the vCard
            $vcard->categories()->delete(); // Clear existing categories
            $vcard->categories()->createMany($request->input('categories'));

            return response()->json(['message' => 'Categories updated successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update categories', 'exception' => $e->getMessage()], 500);
        }
    }
}