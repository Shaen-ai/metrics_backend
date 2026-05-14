<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use JsonException;

class PlannerInquiryMailable extends Mailable
{
    use Queueable, SerializesModels;

    public string $designJsonPretty;

    /** @var array<string, mixed> */
    public array $designSummary;

    public bool $previewAttached = false;

    private ?string $previewAttachmentData = null;

    private ?string $previewAttachmentMime = null;

    private ?string $previewAttachmentName = null;

    /**
     * @param  array<string, mixed>  $design
     */
    public function __construct(
        public User $admin,
        public string $customerName,
        public string $customerEmail,
        public string $plannerType,
        public string $plannerLabel,
        public ?string $notes,
        public array $design,
    ) {
        $design = $this->extractPreviewAttachment($design);
        $design = $this->enrichDesignWithMaterialReferences($design);
        $design = $this->enrichDesignWithCatalogItemReferences($design);
        $this->design = $design;
        $this->previewAttached = $this->previewAttachmentData !== null;
        $this->designSummary = $this->buildDesignSummary($this->design);

        try {
            $this->designJsonPretty = json_encode(
                $this->design,
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        } catch (JsonException) {
            $this->designJsonPretty = '{"error":"Design could not be encoded as JSON."}';
        }
    }

    public function envelope(): Envelope
    {
        $safeCustomer = Str::limit(preg_replace('/[\r\n]+/', ' ', $this->customerName) ?? '', 80, '');
        $safeLabel = Str::limit(preg_replace('/[\r\n]+/', ' ', $this->plannerLabel) ?? '', 60, '');

        return new Envelope(
            subject: 'Planner design: '.$safeLabel.' — '.$safeCustomer,
            replyTo: [
                new Address($this->customerEmail, Str::limit($this->customerName, 70, '') ?: null),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.planner-inquiry',
            text: 'emails.planner-inquiry-text',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->previewAttachmentData === null || $this->previewAttachmentData === '') {
            return [];
        }

        $name = $this->previewAttachmentName ?? 'interior-design-preview.jpg';
        $mime = $this->previewAttachmentMime ?? 'image/jpeg';
        $data = $this->previewAttachmentData;

        return [
            Attachment::fromData(fn () => $data, $name)->withMime($mime),
        ];
    }

    /**
     * @param  array<string, mixed>  $design
     * @return array<string, mixed>
     */
    private function extractPreviewAttachment(array $design): array
    {
        $b64 = $this->stringValue($design['previewImageBase64'] ?? null);
        if ($b64 !== null) {
            $raw = base64_decode($b64, true);
            if ($raw !== false && $raw !== '' && strlen($raw) <= 5_000_000) {
                $mime = $this->stringValue($design['previewImageMimeType'] ?? null) ?? 'image/jpeg';
                $this->previewAttachmentData = $raw;
                $this->previewAttachmentMime = $mime;
                $this->previewAttachmentName = str_contains(strtolower($mime), 'png')
                    ? 'interior-design-preview.png'
                    : 'interior-design-preview.jpg';
            }
        }
        unset($design['previewImageBase64'], $design['previewImageMimeType']);

        return $design;
    }

    /**
     * @param  array<string, mixed>  $design
     * @return array<string, mixed>
     */
    private function enrichDesignWithCatalogItemReferences(array $design): array
    {
        if (($design['variant'] ?? '') !== 'interior-design') {
            return $design;
        }

        $idMap = [];
        $brief = $this->arrayValue($design['designBrief'] ?? null);
        if ($brief !== null) {
            foreach ($this->arrayValue($brief['selectedCatalogIds'] ?? null) ?? [] as $idRaw) {
                $id = is_string($idRaw) ? trim($idRaw) : (is_int($idRaw) ? (string) $idRaw : null);
                if ($id !== null && $id !== '') {
                    $idMap[$id] = $id;
                }
            }
        }
        foreach ($this->arrayValue($design['preferredCatalogIdsForAi'] ?? null) ?? [] as $idRaw) {
            $id = is_string($idRaw) ? trim($idRaw) : (is_int($idRaw) ? (string) $idRaw : null);
            if ($id !== null && $id !== '') {
                $idMap[$id] = $id;
            }
        }

        if (count($idMap) === 0) {
            $design['catalogReferences'] = [];

            return $design;
        }

        $ids = array_values($idMap);
        $catalogItems = $this->admin->catalogItems()->whereIn('id', $ids)->get();
        $refs = [];
        foreach ($catalogItems as $item) {
            $desc = $this->stringValue($item->description ?? null);
            if ($desc !== null) {
                $desc = Str::limit(trim(strip_tags($desc)), 400, '…');
            }
            $refs[] = [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category,
                'planner_subcategory' => $item->planner_subcategory,
                'price' => $item->price !== null ? (float) $item->price : null,
                'currency' => $item->currency,
                'unit' => $item->unit,
                'width' => $item->width !== null ? (float) $item->width : null,
                'depth' => $item->depth !== null ? (float) $item->depth : null,
                'height' => $item->height !== null ? (float) $item->height : null,
                'dimension_unit' => $item->dimension_unit,
                'description' => $desc,
            ];
        }
        $design['catalogReferences'] = $refs;
        $resolved = [];
        foreach ($refs as $r) {
            if (isset($r['id']) && is_string($r['id'])) {
                $resolved[$r['id']] = true;
            }
        }
        $missing = array_values(array_filter($ids, fn (string $id) => ! isset($resolved[$id])));
        if (count($missing) > 0) {
            $design['catalogSkuNotInDatabase'] = $missing;
        }

        return $design;
    }

    /**
     * @param  array<string, mixed>  $design
     * @return array<string, mixed>
     */
    private function enrichDesignWithMaterialReferences(array $design): array
    {
        $ids = $this->collectMaterialIds($design);
        if (count($ids) === 0) {
            return $design;
        }

        $existingReferences = $this->arrayValue($design['materialReferences'] ?? null) ?? [];
        $referencesById = [];
        foreach ($existingReferences as $rawReference) {
            $reference = $this->arrayValue($rawReference);
            if ($reference === null) {
                continue;
            }
            $id = $this->stringValue($reference['id'] ?? null);
            if ($id !== null) {
                $referencesById[$id] = $reference;
            }
        }

        $materials = $this->admin
            ->materials()
            ->whereIn('id', $ids)
            ->get();

        foreach ($materials as $material) {
            $referencesById[$material->id] = [
                'id' => $material->id,
                'name' => $material->name,
                'manufacturer' => $material->manufacturer,
                'type' => $material->type,
                'types' => $material->types ?? [$material->type],
                'category' => $material->category,
                'categories' => $material->categories ?? [$material->category],
                'color' => $material->color_code ?: ($material->color_hex ?: $material->color),
                'pricePerUnit' => $material->price_per_unit !== null ? (float) $material->price_per_unit : null,
                'currency' => $material->currency,
                'unit' => $material->unit,
            ];
        }

        $design['materialReferences'] = array_values($referencesById);

        return $design;
    }

    /**
     * @param  array<string, mixed>  $design
     * @return array<int, string>
     */
    private function collectMaterialIds(array $design): array
    {
        $ids = [];

        foreach ($this->arrayValue($design['materialReferences'] ?? null) ?? [] as $rawReference) {
            $reference = $this->arrayValue($rawReference);
            if ($reference !== null) {
                $this->addMaterialId($ids, $this->stringValue($reference['id'] ?? null));
            }
        }

        foreach ($this->arrayValue($design['placedItems'] ?? null) ?? [] as $rawItem) {
            $item = $this->arrayValue($rawItem);
            if ($item === null) {
                continue;
            }

            $this->addMaterialId($ids, $this->stringValue($item['gltfFinishMaterialId'] ?? null));

            $cushions = $this->arrayValue($item['outdoorCushionConfig'] ?? null);
            if ($cushions === null) {
                continue;
            }

            foreach ($this->arrayValue($cushions['seatMaterialIds'] ?? null) ?? [] as $id) {
                $this->addMaterialId($ids, $this->stringValue($id));
            }
            foreach ($this->arrayValue($cushions['backMaterialIds'] ?? null) ?? [] as $id) {
                $this->addMaterialId($ids, $this->stringValue($id));
            }
        }

        return array_values($ids);
    }

    /**
     * @param  array<string, string>  $ids
     */
    private function addMaterialId(array &$ids, ?string $id): void
    {
        if ($id !== null) {
            $ids[$id] = $id;
        }
    }

    /**
     * @param  array<string, mixed>  $design
     * @return array<string, mixed>
     */
    private function buildDesignSummary(array $design): array
    {
        return [
            'overview' => $this->buildOverviewSummary($design),
            'products' => $this->buildProductSummary($design),
            'materials' => $this->buildMaterialSummary($design),
            'interior_brief' => $this->buildInteriorBriefRows($design),
            'interior_chat' => $this->buildInteriorChatRows($design),
        ];
    }

    /**
     * @param  array<string, mixed>  $design
     * @return array<int, array{label: string, value: string}>
     */
    private function buildInteriorBriefRows(array $design): array
    {
        if (($design['variant'] ?? '') !== 'interior-design') {
            return [];
        }

        $rows = [];
        $brief = $this->arrayValue($design['designBrief'] ?? null);

        if ($brief !== null) {
            $map = [
                'subject' => 'Subject',
                'arrangement' => 'Arrangement',
                'context' => 'Context',
                'composition' => 'Composition (materials / finishes)',
                'style' => 'Style',
            ];
            foreach ($map as $key => $label) {
                $v = $this->stringValue($brief[$key] ?? null);
                if ($v !== null) {
                    $rows[] = ['label' => $label, 'value' => Str::limit($v, 2500, '…')];
                }
            }
        }

        $rp = $this->stringValue($design['renderPromptUsed'] ?? null);
        if ($rp !== null) {
            $rows[] = ['label' => 'Full render prompt used', 'value' => Str::limit($rp, 4000, '…')];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $design
     * @return array<int, array{role: string, content: string}>
     */
    private function buildInteriorChatRows(array $design): array
    {
        if (($design['variant'] ?? '') !== 'interior-design') {
            return [];
        }

        $chat = $this->arrayValue($design['chatTranscript'] ?? null) ?? [];
        $out = [];

        foreach ($chat as $row) {
            $r = $this->arrayValue($row);
            if ($r === null) {
                continue;
            }
            $role = $this->stringValue($r['role'] ?? null) ?? '?';
            $content = $this->stringValue($r['content'] ?? null) ?? '';
            if ($content === '') {
                continue;
            }
            $out[] = [
                'role' => Str::title($role),
                'content' => Str::limit($content, 2000, '…'),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $design
     * @return array<int, array{label: string, value: string}>
     */
    private function buildOverviewSummary(array $design): array
    {
        if (($design['variant'] ?? '') === 'interior-design') {
            $overview = [
                ['label' => 'Planner', 'value' => $this->stringValue($design['plannerDisplayName'] ?? null) ?? $this->plannerLabel],
            ];
            $sid = $this->stringValue($design['sessionId'] ?? null);
            if ($sid !== null) {
                $overview[] = ['label' => 'Session id', 'value' => $sid];
            }
            $imgId = $this->stringValue($design['selectedGeneratedImageId'] ?? null);
            if ($imgId !== null) {
                $overview[] = ['label' => 'Selected render', 'value' => $imgId];
            }
            $tp = $this->stringValue($design['textPrompt'] ?? null);
            if ($tp !== null) {
                $overview[] = ['label' => 'Customer design prompt', 'value' => Str::limit($tp, 1200, '…')];
            }
            $pins = $this->arrayValue($design['preferredCatalogIdsForAi'] ?? null) ?? [];
            if (count($pins) > 0) {
                $labels = [];
                foreach ($pins as $p) {
                    $labels[] = is_string($p) ? $p : (is_scalar($p) ? (string) $p : '');
                }
                $labels = array_values(array_filter($labels, fn ($s) => $s !== ''));
                if (count($labels) > 0) {
                    $overview[] = ['label' => 'Customer prioritized SKUs', 'value' => implode(', ', $labels)];
                }
            }
            $missing = $this->arrayValue($design['catalogSkuNotInDatabase'] ?? null) ?? [];
            if (count($missing) > 0) {
                $overview[] = ['label' => 'SKUs not found in catalog database', 'value' => implode(', ', array_map(fn ($m) => is_string($m) ? $m : (string) $m, $missing))];
            }

            return $overview;
        }

        $overview = [
            ['label' => 'Planner', 'value' => $this->stringValue($design['plannerDisplayName'] ?? null) ?? $this->plannerLabel],
        ];

        $room = $this->arrayValue($design['room'] ?? null);
        if ($room !== null) {
            $roomSize = $this->formatDimensions(
                $this->floatValue($room['width'] ?? null),
                $this->floatValue($room['depth'] ?? null),
                $this->floatValue($room['height'] ?? null),
            );
            if ($roomSize !== null) {
                $overview[] = ['label' => 'Space size', 'value' => $roomSize];
            }

            $floor = $this->humanizeKey($this->stringValue($room['floorStyle'] ?? null));
            if ($floor !== null) {
                $overview[] = ['label' => 'Floor / surface', 'value' => $floor];
            }
        }

        $items = $this->arrayValue($design['placedItems'] ?? null) ?? [];
        $overview[] = ['label' => 'Products placed', 'value' => (string) count($items)];

        return $overview;
    }

    /**
     * @param  array<string, mixed>  $design
     * @return array<int, array{title: string, details: array<int, array{label: string, value: string}>}>
     */
    private function buildProductSummary(array $design): array
    {
        if (($design['variant'] ?? '') === 'interior-design') {
            $refs = $this->arrayValue($design['catalogReferences'] ?? null) ?? [];
            $products = [];

            foreach (array_values($refs) as $index => $rawRef) {
                $ref = $this->arrayValue($rawRef);
                if ($ref === null) {
                    continue;
                }

                $name = $this->stringValue($ref['name'] ?? null) ?? 'Product';
                $details = [];

                $sku = $this->stringValue($ref['id'] ?? null);
                if ($sku !== null) {
                    $details[] = ['label' => 'SKU', 'value' => $sku];
                }

                $category = $this->stringValue($ref['category'] ?? null);
                if ($category !== null) {
                    $details[] = ['label' => 'Category', 'value' => $category];
                }

                $sub = $this->stringValue($ref['planner_subcategory'] ?? null);
                if ($sub !== null) {
                    $details[] = ['label' => 'Planner subcategory', 'value' => $sub];
                }

                $price = $this->floatValue($ref['price'] ?? null);
                $currency = $this->stringValue($ref['currency'] ?? null) ?? '';
                if ($price !== null) {
                    $details[] = ['label' => 'List price', 'value' => trim($this->formatNumber($price).' '.$currency)];
                }

                $unit = $this->stringValue($ref['unit'] ?? null);
                if ($unit !== null) {
                    $details[] = ['label' => 'Unit', 'value' => $unit];
                }

                $wu = $this->floatValue($ref['width'] ?? null);
                $du = $this->floatValue($ref['depth'] ?? null);
                $hu = $this->floatValue($ref['height'] ?? null);
                $dimU = $this->stringValue($ref['dimension_unit'] ?? null);
                if ($wu !== null && $du !== null && $hu !== null && $dimU !== null) {
                    $details[] = ['label' => 'Dimensions (W × D × H)', 'value' => $this->formatNumber($wu).' × '.$this->formatNumber($du).' × '.$this->formatNumber($hu).' '.$dimU];
                }

                $desc = $this->stringValue($ref['description'] ?? null);
                if ($desc !== null) {
                    $details[] = ['label' => 'Description', 'value' => $desc];
                }

                $products[] = [
                    'title' => ($index + 1).'. '.$name,
                    'details' => $details,
                ];
            }

            return $products;
        }

        $items = $this->arrayValue($design['placedItems'] ?? null) ?? [];
        $materialNames = $this->materialNamesById($design);
        $products = [];

        foreach (array_values($items) as $index => $rawItem) {
            $item = $this->arrayValue($rawItem);
            if ($item === null) {
                continue;
            }

            $name = $this->stringValue($item['catalogName'] ?? null)
                ?? $this->stringValue($item['catalogId'] ?? null)
                ?? 'Product';
            $details = [];

            $category = $this->humanizeKey($this->stringValue($item['category'] ?? null));
            if ($category !== null) {
                $details[] = ['label' => 'Category', 'value' => $category];
            }

            $vendor = $this->stringValue($item['catalogVendor'] ?? null);
            if ($vendor !== null) {
                $details[] = ['label' => 'Vendor', 'value' => $vendor];
            }

            $price = $this->floatValue($item['catalogPrice'] ?? null);
            if ($price !== null) {
                $details[] = ['label' => 'Catalog price', 'value' => $this->formatNumber($price)];
            }

            $size = $this->arrayValue($item['sizeM'] ?? null);
            if ($size !== null) {
                $dimensions = $this->formatDimensions(
                    $this->floatValue($size['width'] ?? null),
                    $this->floatValue($size['depth'] ?? null),
                    $this->floatValue($size['height'] ?? null),
                );
                if ($dimensions !== null) {
                    $details[] = ['label' => 'Size (W x D x H)', 'value' => $dimensions];
                }
            }

            $finishId = $this->stringValue($item['gltfFinishMaterialId'] ?? null);
            if ($finishId !== null) {
                $details[] = ['label' => 'Finish material', 'value' => $this->materialLabel($finishId, $materialNames)];
            }

            $cushions = $this->arrayValue($item['outdoorCushionConfig'] ?? null);
            if (($cushions['enabled'] ?? false) === true) {
                $this->appendOutdoorCushionDetails($details, $cushions, $materialNames);
            }

            $products[] = [
                'title' => ($index + 1).'. '.$name,
                'details' => $details,
            ];
        }

        return $products;
    }

    /**
     * @param  array<string, mixed>  $design
     * @return array<int, array{name: string, id: string, count: int}>
     */
    private function buildMaterialSummary(array $design): array
    {
        if (($design['variant'] ?? '') === 'interior-design') {
            return [];
        }

        $items = $this->arrayValue($design['placedItems'] ?? null) ?? [];
        $materialNames = $this->materialNamesById($design);
        $counts = [];

        foreach ($items as $rawItem) {
            $item = $this->arrayValue($rawItem);
            if ($item === null) {
                continue;
            }
            $this->countMaterial($counts, $this->stringValue($item['gltfFinishMaterialId'] ?? null));

            $cushions = $this->arrayValue($item['outdoorCushionConfig'] ?? null);
            if ($cushions === null) {
                continue;
            }
            foreach ($this->arrayValue($cushions['seatMaterialIds'] ?? null) ?? [] as $id) {
                $this->countMaterial($counts, $this->stringValue($id));
            }
            foreach ($this->arrayValue($cushions['backMaterialIds'] ?? null) ?? [] as $id) {
                $this->countMaterial($counts, $this->stringValue($id));
            }
        }

        $materials = [];
        foreach ($counts as $id => $count) {
            $materials[] = [
                'id' => $id,
                'name' => $materialNames[$id] ?? 'Unknown material',
                'count' => $count,
            ];
        }

        return $materials;
    }

    /**
     * @param  array<int, array{label: string, value: string}>  $details
     * @param  array<string, mixed>  $cushions
     * @param  array<string, string>  $materialNames
     */
    private function appendOutdoorCushionDetails(array &$details, array $cushions, array $materialNames): void
    {
        $seatLayout = $this->humanizeKey($this->stringValue($cushions['seatLayout'] ?? null));
        if ($seatLayout !== null) {
            $details[] = ['label' => 'Cushion seat layout', 'value' => $seatLayout];
        }

        $segmentWidths = $this->arrayValue($cushions['seatSegmentWidthsM'] ?? null) ?? [];
        if (count($segmentWidths) > 0) {
            $details[] = [
                'label' => 'Seat segment widths',
                'value' => implode(', ', array_map(fn ($w) => $this->formatCm($this->floatValue($w)), $segmentWidths)),
            ];
        }

        $gap = $this->floatValue($cushions['gapBetweenSegmentsM'] ?? null);
        if ($gap !== null) {
            $details[] = ['label' => 'Gap between cushions', 'value' => $this->formatCm($gap)];
        }

        $seatThickness = $this->floatValue($cushions['seatThicknessM'] ?? null);
        if ($seatThickness !== null) {
            $details[] = ['label' => 'Seat cushion thickness', 'value' => $this->formatCm($seatThickness)];
        }

        $backThickness = $this->floatValue($cushions['backThicknessM'] ?? null);
        if ($backThickness !== null) {
            $details[] = ['label' => 'Back cushion thickness', 'value' => $this->formatCm($backThickness)];
        }

        $seatMaterials = $this->materialListLabel($this->arrayValue($cushions['seatMaterialIds'] ?? null) ?? [], $materialNames);
        if ($seatMaterials !== null) {
            $details[] = ['label' => 'Seat cushion material', 'value' => $seatMaterials];
        }

        $backMaterials = $this->materialListLabel($this->arrayValue($cushions['backMaterialIds'] ?? null) ?? [], $materialNames);
        if ($backMaterials !== null) {
            $details[] = ['label' => 'Back cushion material', 'value' => $backMaterials];
        }
    }

    /**
     * @param  array<string, mixed>  $design
     * @return array<string, string>
     */
    private function materialNamesById(array $design): array
    {
        $names = [];
        foreach ($this->arrayValue($design['materialReferences'] ?? null) ?? [] as $rawMaterial) {
            $material = $this->arrayValue($rawMaterial);
            if ($material === null) {
                continue;
            }
            $id = $this->stringValue($material['id'] ?? null);
            $name = $this->stringValue($material['name'] ?? null);
            if ($id !== null && $name !== null) {
                $names[$id] = $name;
            }
        }

        return $names;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function countMaterial(array &$counts, ?string $id): void
    {
        if ($id === null) {
            return;
        }
        $counts[$id] = ($counts[$id] ?? 0) + 1;
    }

    /**
     * @param  array<int, mixed>  $ids
     * @param  array<string, string>  $materialNames
     */
    private function materialListLabel(array $ids, array $materialNames): ?string
    {
        $counts = [];
        foreach ($ids as $id) {
            $this->countMaterial($counts, $this->stringValue($id));
        }
        if (count($counts) === 0) {
            return null;
        }

        $labels = [];
        foreach ($counts as $id => $count) {
            $label = $this->materialLabel($id, $materialNames);
            $labels[] = $count > 1 ? $label.' x '.$count : $label;
        }

        return implode(', ', $labels);
    }

    /**
     * @param  array<string, string>  $materialNames
     */
    private function materialLabel(string $id, array $materialNames): string
    {
        return ($materialNames[$id] ?? 'Unknown material').' ('.$id.')';
    }

    /**
     * @return array<string|int, mixed>|null
     */
    private function arrayValue(mixed $value): ?array
    {
        return is_array($value) ? $value : null;
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function floatValue(mixed $value): ?float
    {
        return is_int($value) || is_float($value) ? (float) $value : null;
    }

    private function formatDimensions(?float $widthM, ?float $depthM, ?float $heightM): ?string
    {
        if ($widthM === null || $depthM === null || $heightM === null) {
            return null;
        }

        return $this->formatCm($widthM).' x '.$this->formatCm($depthM).' x '.$this->formatCm($heightM);
    }

    private function formatCm(?float $meters): string
    {
        if ($meters === null) {
            return 'n/a';
        }

        return $this->formatNumber($meters * 100).' cm';
    }

    private function formatNumber(float $value): string
    {
        $rounded = round($value, 2);

        return rtrim(rtrim(number_format($rounded, 2, '.', ''), '0'), '.');
    }

    private function humanizeKey(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Str::of($value)->replace(['-', '_'], ' ')->title()->toString();
    }
}
