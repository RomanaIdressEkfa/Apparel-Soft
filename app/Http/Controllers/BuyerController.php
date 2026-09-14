<?php

namespace App\Http\Controllers;

use App\Models\Buyer;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BuyerController extends Controller
{
    private const ALLOWED_RICH_TEXT_TAGS = '<p><br><b><strong><i><em><u><ul><ol><li><a><span><h1><h2><h3><blockquote>';

    public function index()
    {
        $counts = Task::selectRaw('buyer, COUNT(*) as total')
            ->whereNotNull('buyer')
            ->groupBy('buyer')
            ->pluck('total', 'buyer');

        $buyers = Buyer::orderBy('name')->get()->map(fn (Buyer $buyer) => [
            'id' => $buyer->id,
            'name' => $buyer->name,
            'brand' => $buyer->brand,
            'contact_person' => $buyer->contact_person,
            'email' => $buyer->email,
            'phone' => $buyer->phone,
            'country' => $buyer->country,
            'notes' => $buyer->notes,
            'task_count' => (int) ($counts[$buyer->name] ?? 0),
        ]);

        return response()->json(['buyers' => $buyers]);
    }

    public function exportCsv(): StreamedResponse
    {
        $counts = Task::selectRaw('buyer, COUNT(*) as total')
            ->whereNotNull('buyer')
            ->groupBy('buyer')
            ->pluck('total', 'buyer');

        $buyers = Buyer::orderBy('name')->get();
        $filename = 'apparel-soft-track-buyers-'.now()->format('Y-m-d').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->streamDownload(function () use ($buyers, $counts) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Buyer', 'Brand', 'Contact Person', 'Email', 'Phone', 'Country', 'Notes', 'Styles', 'Added On']);

            foreach ($buyers as $buyer) {
                fputcsv($out, [
                    $buyer->id,
                    $buyer->name,
                    $buyer->brand,
                    $buyer->contact_person,
                    $buyer->email,
                    $buyer->phone,
                    $buyer->country,
                    trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $buyer->notes)))),
                    (int) ($counts[$buyer->name] ?? 0),
                    $buyer->created_at?->format('Y-m-d'),
                ]);
            }

            fclose($out);
        }, $filename, $headers);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $buyer = Buyer::create($data);

        return response()->json(['ok' => true, 'id' => $buyer->id]);
    }

    public function update(Request $request, Buyer $buyer)
    {
        $data = $this->validated($request, $buyer->id);
        $previousName = $buyer->name;

        $buyer->update($data);

        if ($previousName !== $buyer->name) {
            Task::where('buyer', $previousName)->update(['buyer' => $buyer->name]);
        }

        return response()->json(['ok' => true, 'id' => $buyer->id]);
    }

    public function destroy(Buyer $buyer)
    {
        $buyer->delete();

        return response()->json(['ok' => true]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('buyers', 'name')->ignore($ignoreId)],
            'brand' => ['nullable', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:60'],
            'country' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        if (! empty($data['notes'])) {
            $data['notes'] = strip_tags($data['notes'], self::ALLOWED_RICH_TEXT_TAGS);
        }

        return $data;
    }
}
