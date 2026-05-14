<?php

namespace App\Mail;

use App\Models\CustomDesign;
use DateTime;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomDesignSubmittedMailable extends Mailable
{
    use Queueable, SerializesModels;

    public string $designJsonPretty;

    /** @var array<string, mixed> */
    public array $designSummary;

    public function __construct(public CustomDesign $customDesign)
    {
        $this->designJsonPretty = json_encode(
            $customDesign->design,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) ?: '{"error":"Design could not be encoded as JSON."}';

        $this->designSummary = $this->buildDesignSummary($customDesign->design ?? []);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Custom design submitted: '.($this->customDesign->room_name ?: 'Untitled room'),
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.custom-design-submitted',
            text: 'emails.custom-design-submitted-text',
        );
    }

    /**
     * @param  array<string, mixed>  $design
     * @return array<string, mixed>
     */
    private function buildDesignSummary(array $design): array
    {
        $overview = [];

        $plannerName = $this->stringValue($design['plannerDisplayName'] ?? null) ?? 'Custom planner';
        $overview[] = ['label' => 'Planner', 'value' => $plannerName];

        $generatedAt = $this->stringValue($design['generatedAt'] ?? null);
        if ($generatedAt !== null) {
            try {
                $dt = new DateTime($generatedAt);
                $overview[] = ['label' => 'Generated at', 'value' => $dt->format('Y-m-d H:i').' UTC'];
            } catch (Exception) {
                $overview[] = ['label' => 'Generated at', 'value' => $generatedAt];
            }
        }

        $mode = $this->stringValue($design['mode'] ?? null);
        if ($mode !== null) {
            $overview[] = ['label' => 'Mode', 'value' => ucfirst($mode)];
        }

        $sheetDraft = $this->arrayValue($design['sheetDraft'] ?? null) ?? [];

        $unit = $this->stringValue($sheetDraft['unit'] ?? null);
        if ($unit !== null) {
            $overview[] = ['label' => 'Unit', 'value' => strtoupper($unit)];
        }

        $gridMm = $sheetDraft['gridMm'] ?? null;
        if (is_numeric($gridMm)) {
            $overview[] = ['label' => 'Grid size', 'value' => $gridMm.' mm'];
        }

        // Canvas settings (snap, ortho, grid)
        $settings = [];
        if (isset($sheetDraft['snap'])) {
            $settings[] = 'Snap: '.($sheetDraft['snap'] ? 'on' : 'off');
        }
        if (isset($sheetDraft['ortho'])) {
            $settings[] = 'Ortho: '.($sheetDraft['ortho'] ? 'on' : 'off');
        }
        if (isset($sheetDraft['showGrid'])) {
            $settings[] = 'Grid: '.($sheetDraft['showGrid'] ? 'visible' : 'hidden');
        }
        if (! empty($settings)) {
            $overview[] = ['label' => 'Canvas', 'value' => implode(' · ', $settings)];
        }

        // Layers
        $layers = [];
        foreach ($this->arrayValue($sheetDraft['layers'] ?? null) ?? [] as $rawLayer) {
            $layer = $this->arrayValue($rawLayer);
            if ($layer === null) {
                continue;
            }
            $name = $this->stringValue($layer['name'] ?? null) ?? 'Untitled layer';
            $flags = [];
            if (! ($layer['visible'] ?? true)) {
                $flags[] = 'hidden';
            }
            if ($layer['locked'] ?? false) {
                $flags[] = 'locked';
            }
            $layers[] = $name.(count($flags) > 0 ? ' ('.implode(', ', $flags).')' : '');
        }

        // Elements from Fabric.js canvas serialization
        $elementCount = 0;
        $elementTypes = [];
        $fabric = $this->arrayValue($sheetDraft['fabric'] ?? null);
        if ($fabric !== null) {
            foreach ($this->arrayValue($fabric['objects'] ?? null) ?? [] as $rawObj) {
                $obj = $this->arrayValue($rawObj);
                if ($obj === null) {
                    continue;
                }
                $type = $this->stringValue($obj['type'] ?? null) ?? 'object';
                $elementTypes[$type] = ($elementTypes[$type] ?? 0) + 1;
                $elementCount++;
            }
        }

        return [
            'overview' => $overview,
            'layers' => $layers,
            'elementCount' => $elementCount,
            'elementTypes' => $elementTypes,
        ];
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
}
