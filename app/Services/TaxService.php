<?php

namespace App\Services;

use App\Http\Resources\TaxRateResource;
use App\Models\TaxRate;

class TaxService
{
    /**
     * Get all tax rates based on the provided request filters.
     *
     * @param Request $request
     * @return mixed
     */
    public function get(object $request)
    {
        $rows = TaxRate::where('deleted_at', null)->latest()->paginate(10);

        return TaxRateResource::collection($rows);
    }

    /**
     * Store Tax Rate
     *
     * @param Request $request
     * @param string $id
     * @return \App\Models\TaxRate
     */
    public function store(object $request, $id = NULL)
    {
        $taxrate = $id === null ? new TaxRate() : TaxRate::where('id', $id)->firstOrFail();
        $taxrate->name = $request->name;
        $taxrate->percentage = $request->percentage;
        $taxrate->save();

        return $taxrate;
    }

    /**
     * Delete Tax Rate
     *
     * @param Request $request
     * @param string $id
     * @return \App\Models\TaxRate
     */
    public function delete($id)
    {
        $taxrate = TaxRate::findOrFail($id);
        $taxrate->status = 'inactive';
        $taxrate->deleted_at = date('Y-m-d H:i:s');
        $taxrate->save();

        return $taxrate;
    } 
}
