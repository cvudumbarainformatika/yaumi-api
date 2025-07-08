<?php
// app/Http/Controllers/Api/V1/CompanyController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function show()
    {
        $company = Company::first();
        return response()->json($company);
    }

    public function update(Request $request)
    {
        $company = Company::firstOrCreate([]);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'owner_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'address' => 'nullable|string|max:255',
            'npwp' => 'nullable|string|max:50',
            'tax_label' => 'nullable|string|max:50',
            'is_tax_active' => 'boolean',
            'logo' => 'nullable|image|max:2048'
        ]);

        if ($request->hasFile('logo')) {
            // Optional: delete old logo
            if ($company->logo && Storage::exists($company->logo)) {
                Storage::delete($company->logo);
            }

            $validated['logo'] = $request->file('logo')->store('logos');
        }

        $company->update($validated);

        return response()->json([
            'message' => 'Data perusahaan berhasil diperbarui',
            'data' => $company
        ]);
    }
}
