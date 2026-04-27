<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'adminId' => $this->admin_id,
            'modeId' => $this->mode_id,
            'manufacturer' => $this->manufacturer,
            'subModeId' => $this->sub_mode_id,
            'name' => $this->name,
            'type' => $this->type,
            'types' => $this->types ?? [$this->type],
            'category' => $this->category,
            'categories' => $this->categories ?? [$this->category],
            'color' => $this->color,
            'colorHex' => $this->color_hex,
            'colorCode' => $this->color_code,
            'price' => (float) $this->price,
            'pricePerUnit' => (float) $this->price_per_unit,
            'currency' => $this->currency,
            'unit' => $this->unit,
            'image' => $this->image,
            'imageUrl' => $this->image_url,
            // Laminate/wood/worktop sheet metadata; callers that do not need it
            // may ignore these fields. Defaults mirror getSheetSpec() on the
            // frontend (360 × 180 cm, grain along sheet width, 3 mm kerf).
            'sheetWidthCm' => $this->sheet_width_cm !== null
                ? (float) $this->sheet_width_cm
                : 360.0,
            'sheetHeightCm' => $this->sheet_height_cm !== null
                ? (float) $this->sheet_height_cm
                : 180.0,
            'grainDirection' => $this->grain_direction ?? 'along_width',
            'kerfMm' => $this->kerf_mm !== null
                ? (float) $this->kerf_mm
                : 3.0,
            'isActive' => $this->is_active,
        ];
    }
}
