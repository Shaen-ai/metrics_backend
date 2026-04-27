<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'manufacturer' => $this->manufacturer,
            'externalCode' => $this->external_code,
            'name' => $this->name,
            'type' => $this->type,
            'types' => $this->types ?? [$this->type],
            'category' => $this->category,
            'categories' => $this->categories ?? [$this->category],
            'color' => $this->color,
            'colorHex' => $this->color_hex,
            'colorCode' => $this->color_code,
            'unit' => $this->unit,
            'imageUrl' => $this->image_url,
            'sourceUrl' => $this->source_url,
            'sheetWidthCm' => $this->sheet_width_cm !== null
                ? (float) $this->sheet_width_cm
                : null,
            'sheetHeightCm' => $this->sheet_height_cm !== null
                ? (float) $this->sheet_height_cm
                : null,
            'grainDirection' => $this->grain_direction,
            'kerfMm' => $this->kerf_mm !== null
                ? (float) $this->kerf_mm
                : null,
            'sortOrder' => (int) $this->sort_order,
        ];
    }
}
