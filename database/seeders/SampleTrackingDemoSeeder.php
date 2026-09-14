<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskImage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Loads a set of rows taken from the merchandiser's real Sample Tracking List so
 * every row colour, milestone and block field can be seen working end to end.
 */
class SampleTrackingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->wipeExistingTasks();

        $author = User::orderBy('id')->first();

        foreach ($this->rows() as $row) {
            $swatch = $row['_swatch'];
            $note = $row['_note'] ?? null;
            unset($row['_swatch'], $row['_note']);

            $task = Task::create($row + [
                'sample_type' => 'Development',
                'created_by' => $author?->id,
            ]);

            $this->attachSwatch($task, $swatch);

            if ($note && $author) {
                TaskComment::create([
                    'task_id' => $task->id,
                    'user_id' => $author->id,
                    'type' => $note[0],
                    'text' => '<p>'.e($note[1]).'</p>',
                ]);
            }
        }
    }

    private function wipeExistingTasks(): void
    {
        foreach (TaskImage::all() as $image) {
            $path = storage_path('app/public/task-images/'.$image->filename);
            if (is_file($path)) {
                @unlink($path);
            }
        }

        DB::table('task_comments')->delete();
        DB::table('task_images')->delete();
        DB::table('tasks')->delete();
    }

    /**
     * Paints a soft denim swatch so the IMAGE column is populated. Replace these
     * with the real style photos by editing a style and uploading its picture.
     */
    private function attachSwatch(Task $task, array $swatch): void
    {
        [$r, $g, $b] = $swatch;

        $w = 320;
        $h = 320;
        $img = imagecreatetruecolor($w, $h);

        $base = imagecolorallocate($img, $r, $g, $b);
        imagefilledrectangle($img, 0, 0, $w, $h, $base);

        // Woven twill texture
        $weave = imagecolorallocatealpha($img, max(0, $r - 28), max(0, $g - 28), max(0, $b - 28), 70);
        for ($i = -$h; $i < $w; $i += 6) {
            imageline($img, $i, 0, $i + $h, $h, $weave);
        }

        // Seam lines, like a garment sketch
        $seam = imagecolorallocate($img, 244, 233, 205);
        imagesetthickness($img, 3);
        imageline($img, 40, 0, 40, $h, $seam);
        imageline($img, $w - 40, 0, $w - 40, $h, $seam);
        imageline($img, 0, 92, $w, 92, $seam);

        $ink = imagecolorallocate($img, 255, 255, 255);
        $label = Str::limit($task->style_code ?? $task->title, 22, '');
        imagestring($img, 5, 18, $h - 34, $label, $ink);

        $filename = 'task'.$task->id.'_'.Str::random(16).'.png';
        $dir = storage_path('app/public/task-images');
        File::ensureDirectoryExists($dir);
        imagepng($img, $dir.'/'.$filename);
        imagedestroy($img);

        TaskImage::create([
            'task_id' => $task->id,
            'filename' => $filename,
            'original_name' => 'demo-swatch.png',
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(): array
    {
        $indigo = [92, 118, 158];
        $lightIndigo = [148, 174, 206];
        $ecru = [214, 198, 176];
        $blush = [220, 196, 192];
        $rinse = [58, 74, 106];

        return [
            [
                'season' => 'SS27', 'dept' => 'MP', 'buyer' => 'H&M Group',
                'title' => 'MP-SS27-4163 BLONDE DENIM SHORT WITH EMBROIDERY',
                'thread' => 'DTM 13-0907 TCX', 'spec' => 'H52069 with 24 CM Length',
                'button_rivet' => 'NCF 2', 'wash_detail' => 'Desize + enzyme + softener',
                'fab_art' => 'R6765-1', 'received_at' => '2026-08-11', 'techpack_handover_date' => '2026-08-15',
                'cutting_status' => 'Done', 'sewing_status' => 'Done',
                'booking_fabric' => 'Booked', 'booking_body_thread' => 'Booked',
                'booking_emb_thread' => 'Booked', 'booking_metalwork' => 'Booked', 'booking_lace' => 'N/A',
                'remarks' => 'Option- 1', 'row_state' => 'active',
                'status' => 'in_progress', 'priority' => 'high', 'sample_qty' => 2,
                '_swatch' => $blush,
                '_note' => ['comment', 'Option 1 uses the booked DTM thread. Waiting on buyer to pick between the two options.'],
            ],
            [
                'season' => 'SS27', 'dept' => 'MP', 'buyer' => 'H&M Group',
                'title' => 'MP-SS27-4163 BLONDE DENIM SHORT WITH EMBROIDERY (OP-2)',
                'thread' => 'TBA', 'spec' => 'H52069 with 24 CM Length', 'wash_detail' => 'TBA',
                'fab_art' => 'S3656-I', 'received_at' => '2026-08-11',
                'cutting_status' => 'Done', 'sewing_status' => 'Done',
                'remarks' => 'Option- 2', 'row_state' => 'active',
                'status' => 'in_progress', 'priority' => 'high', 'sample_qty' => 2,
                '_swatch' => $blush,
            ],
            [
                'season' => 'SS27', 'buyer' => 'H&M Group', 'title' => 'NFD BUTTERFLY DENIM PANT',
                'fab_art' => 'SM260200078- Sim Fabric.', 'techpack_handover_date' => '2026-08-13',
                'cutting_status' => 'Done', 'sewing_status' => 'Done',
                'wash_send_date' => '2026-08-17', 'wash_rcvd_date' => '2026-08-19',
                'sample_submit_date' => '2026-08-21',
                'booking_fabric' => 'In House', 'row_state' => 'active',
                'status' => 'done', 'priority' => 'medium', 'sample_qty' => 1,
                '_swatch' => $blush,
            ],
            [
                'season' => 'SS27', 'dept' => 'OX', 'buyer' => 'H&M Group', 'title' => '4020 CAPYFUN DENIM SHORT',
                'spec' => 'attach', 'button_rivet' => 'NCF 2', 'wash_detail' => 'As per Std.',
                'fab_art' => 'FF-12418', 'received_at' => '2026-08-11', 'techpack_handover_date' => '2026-08-15',
                'cutting_status' => 'Done', 'sewing_status' => 'Done',
                'wash_send_date' => '2026-08-24', 'wash_rcvd_date' => '2026-08-28',
                'sample_submit_date' => '2026-09-01', 'price_note' => 'Done 17-Aug',
                'booking_fabric' => 'Booked', 'booking_body_thread' => 'waitting for swatch',
                'booking_emb_thread' => 'Booked', 'booking_metalwork' => 'Booked', 'booking_lace' => 'Jacron : Booked',
                'row_state' => 'active', 'status' => 'done', 'priority' => 'medium', 'sample_qty' => 3,
                '_swatch' => $lightIndigo,
            ],
            [
                'season' => 'SS27', 'dept' => 'OX', 'buyer' => 'H&M Group', 'title' => 'EYX0726012 LACE HEM JORT',
                'thread' => 'C8658 & C8356', 'spec' => 'As Block V11814',
                'button_rivet' => 'Shank: GA60 26 Ligne, Rivet: AKUM/RVND/30332 9.5MM/MY34',
                'wash_detail' => 'As per Std.', 'fab_art' => 'HAMEEM DENIM S3737',
                'received_at' => '2026-08-13', 'techpack_handover_date' => '2026-08-15',
                'booking_fabric' => 'booked 13-Aug', 'booking_body_thread' => 'booked 13-Aug',
                'booking_emb_thread' => 'N/A', 'booking_metalwork' => 'booked 13-Aug',
                'booking_lace' => 'supplier info pending',
                'remarks' => 'Drop', 'row_state' => 'drop',
                'status' => 'cancelled', 'priority' => 'medium',
                '_swatch' => $indigo,
                '_note' => ['buyer_email', 'Buyer dropped this style — lace supplier could not confirm delivery.'],
            ],
            [
                'season' => 'SS27', 'dept' => 'BG', 'buyer' => 'H&M Group', 'title' => 'EYX0826009 FOLK EMB BALLON JEAN',
                'thread' => 'C-2305 & C-0428', 'spec' => 'E35251', 'wash_detail' => 'As per Std.',
                'fab_art' => 'HAMEEM DENIM S3656-I', 'received_at' => '2026-08-13',
                'techpack_handover_date' => '2026-08-15', 'cutting_status' => 'Done', 'sewing_status' => 'Done',
                'wash_send_date' => '2026-08-26', 'wash_rcvd_date' => '2026-08-30', 'sample_submit_date' => '2026-09-03',
                'booking_fabric' => 'Booked', 'booking_body_thread' => 'Booked', 'booking_emb_thread' => 'Booked',
                'booking_metalwork' => 'N/A', 'booking_lace' => 'Booked',
                'row_state' => 'active', 'status' => 'done', 'priority' => 'medium', 'sample_qty' => 2,
                '_swatch' => $indigo,
            ],
            [
                'season' => 'SS27', 'dept' => 'OG', 'buyer' => 'H&M Group',
                'title' => 'EYX0826012 GRAFFITI DOODLE WIDE LEG JEAN',
                'thread' => 'C-8658', 'spec' => '290913',
                'button_rivet' => '26L Shank button follow Ching Fung ref: GA4, Flat top rivets in NCF91 10L/6mm',
                'wash_detail' => 'As per Std.', 'fab_art' => 'S3656-I',
                'received_at' => '2026-08-15', 'techpack_handover_date' => '2026-08-16',
                'cutting_status' => 'Done', 'sewing_status' => 'Done',
                'wash_send_date' => '2026-08-26', 'wash_rcvd_date' => '2026-08-29', 'sample_submit_date' => '2026-09-02',
                'booking_fabric' => 'Booked', 'booking_body_thread' => 'In House',
                'booking_emb_thread' => 'Contrast & EMB Thread: Booked', 'booking_metalwork' => 'Booked',
                'booking_lace' => 'N/A',
                'row_state' => 'active', 'status' => 'done', 'priority' => 'high', 'sample_qty' => 4,
                '_swatch' => $lightIndigo,
            ],
            [
                'season' => 'SS27', 'dept' => 'OG', 'buyer' => 'H&M Group', 'title' => 'CROSSOVER EXTRA WIDE ZEBRA',
                'thread' => '8 SPI IN C2306', 'spec' => '742998 (add volume for an extra wide look)',
                'button_rivet' => 'Sprayed sand nickel NCF 2 Ching', 'fab_art' => 'ARGON DENIM REG 8902 (RIGID 100% COTTON)',
                'received_at' => '2026-08-16', 'techpack_handover_date' => '2026-08-16',
                'cutting_status' => 'Done', 'sewing_status' => 'Done',
                'wash_send_date' => '2026-08-25', 'wash_rcvd_date' => '2026-08-28', 'sample_submit_date' => '2026-09-01',
                'booking_fabric' => 'Booked', 'booking_body_thread' => 'Booked', 'booking_metalwork' => 'Booked',
                'row_state' => 'active', 'status' => 'done', 'priority' => 'medium', 'sample_qty' => 2,
                '_swatch' => $rinse,
            ],
            [
                'season' => 'SS27', 'dept' => 'OG', 'buyer' => 'H&M Group', 'title' => 'EYX0826014 BALLOON UTILITY CARGO',
                'thread' => 'C2356', 'spec' => 'G66-333', 'button_rivet' => 'as A/W', 'wash_detail' => 'As per Std.',
                'fab_art' => 'S3656-I', 'received_at' => '2026-08-18', 'techpack_handover_date' => '2026-08-22',
                'cutting_status' => 'Done', 'sewing_status' => 'Done',
                'wash_send_date' => '2026-08-28', 'wash_rcvd_date' => '2026-08-31', 'sample_submit_date' => '2026-09-03',
                'booking_fabric' => 'Booked', 'booking_metalwork' => 'Booked', 'booking_lace' => 'Cotton Tape: Booked',
                'row_state' => 'active', 'status' => 'done', 'priority' => 'medium', 'sample_qty' => 3,
                '_swatch' => $indigo,
            ],
            [
                'season' => 'SS27', 'dept' => 'OG', 'buyer' => 'Zara',
                'title' => '4084 TOY STORY BLONDE JEAN',
                'spec' => 'Wide-leg Jean Block', 'fab_art' => 'R6765-1 (Hameem Denim)',
                'received_at' => '2026-08-13', 'techpack_handover_date' => '2026-08-14',
                'remarks' => 'Hold due to license issue', 'row_state' => 'license_hold',
                'status' => 'waiting', 'priority' => 'urgent',
                '_swatch' => $ecru,
                '_note' => ['correction', 'Licensor has not cleared the artwork yet — everything is paused until we hear back.'],
            ],
            [
                'season' => 'SS27', 'dept' => 'OG', 'buyer' => 'Zara', 'title' => 'Barrel Stripe Twill Trouser',
                'thread' => 'DTM', 'spec' => 'Same as 742819', 'wash_detail' => 'H. Enzyme',
                'fab_art' => 'Sim Fabrics', 'received_at' => '2026-08-05',
                'remarks' => 'Hold by Ms. Nishat', 'row_state' => 'hold',
                'status' => 'waiting', 'priority' => 'medium',
                '_swatch' => $blush,
            ],
            [
                'season' => 'SS27', 'dept' => 'OG', 'buyer' => 'H&M Group',
                'title' => 'EYX0826020 TENCEL LIGHT WASH JUMPSUIT',
                'thread' => 'DTM', 'spec' => 'As AW', 'wash_detail' => 'As per std.',
                'fab_art' => '100% Tencel, Envoy-T6994-1', 'received_at' => '2026-08-22',
                'techpack_handover_date' => '2026-08-24', 'cutting_status' => 'Done', 'sewing_status' => 'Running',
                'wash_send_date' => '2026-09-05', 'sample_submit_date' => '2026-09-08',
                'booking_fabric' => 'Booked', 'row_state' => 'active',
                'status' => 'in_progress', 'priority' => 'high', 'sample_qty' => 2,
                '_swatch' => $lightIndigo,
            ],
            [
                'season' => 'SS27', 'dept' => 'BG', 'buyer' => 'H&M Group',
                'title' => 'EYX0826029 STAR JACQUARD CARGO DUNGARE',
                'thread' => 'C2315', 'spec' => 'W91852', 'fab_art' => 'HJ1201 MH',
                'received_at' => '2026-08-29', 'techpack_handover_date' => '2026-08-30',
                'cutting_status' => 'Done', 'sewing_status' => 'Running',
                'wash_send_date' => '2026-09-07', 'wash_rcvd_date' => '2026-09-08', 'sample_submit_date' => '2026-09-09',
                'booking_fabric' => 'Need to collect from NSBD', 'booking_body_thread' => 'Booked',
                'booking_metalwork' => 'Booked',
                'row_state' => 'active', 'status' => 'in_progress', 'priority' => 'urgent', 'sample_qty' => 2,
                '_swatch' => $rinse,
                '_note' => ['reply', 'Told the buyer sample will submit on 9-Sep once wash returns.'],
            ],
            [
                'season' => 'SS27', 'dept' => 'OX', 'buyer' => 'Zara', 'title' => 'EYX0826024 GARMENT DYE JORTS',
                'thread' => 'DTM, Increase SPI 8/9', 'spec' => 'As G65678',
                'fab_art' => 'HAMEEM TEXTILE HT04700-2 PFD', 'received_at' => '2026-08-27',
                'techpack_handover_date' => '2026-09-05',
                'booking_fabric' => 'Booked', 'booking_body_thread' => 'Booked',
                'row_state' => 'active', 'status' => 'new', 'priority' => 'medium',
                '_swatch' => $ecru,
            ],
        ];
    }
}
