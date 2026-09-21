<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * The building: five courts in their real arrangement, operating hours,
 * rental equipment and the pro-shop catalogue.
 *
 * map_x / map_y are metres from the north-west corner of the compound and
 * drive both the 2D facility map and the Three.js scene, so the digital
 * layout and the physical one stay in sync from one source.
 */
class FacilitySeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // ---------------------------------------------------------------- courts
        $courts = [
            [
                'code' => 'Court 1', 'name' => 'Center Court', 'slug' => 'center-court',
                'surface_type' => 'covered', 'surface' => 'Cushioned acrylic', 'hourly_rate' => 150, 'peak_rate' => 200,
                'map_x' => 0, 'map_y' => 0, 'image' => 'photo-1722444732959-f50e30ee29d0',
                'desc' => 'The show court. Full roof, eight-pole LED array, tiered seating for 120 and the only court wired for live scoring. Every tournament final at Bernie is played here.',
            ],
            [
                'code' => 'Court 2', 'name' => 'North Court', 'slug' => 'north-court',
                'surface_type' => 'covered', 'surface' => 'Cushioned acrylic', 'hourly_rate' => 150, 'peak_rate' => 200,
                'map_x' => 14, 'map_y' => 0, 'image' => 'photo-1756477558468-b3e485757470',
                'desc' => 'Covered, wind-free and the most-booked court on the property. Shares the north canopy with Court 3, so a doubles group can take both and rotate.',
            ],
            [
                'code' => 'Court 3', 'name' => 'South Court', 'slug' => 'south-court',
                'surface_type' => 'covered', 'surface' => 'Cushioned acrylic', 'hourly_rate' => 150, 'peak_rate' => 200,
                'map_x' => 28, 'map_y' => 0, 'image' => 'photo-1686721135030-e2ab79e27b16',
                'desc' => 'Identical build to Court 2 and the default court for coaching blocks — the ball machine and the video rig live at the south end.',
            ],
            [
                'code' => 'Court 4', 'name' => 'Riverside Court', 'slug' => 'riverside-court',
                'surface_type' => 'outdoor', 'surface' => 'Post-tension concrete', 'hourly_rate' => 150, 'peak_rate' => 200,
                'map_x' => 0, 'map_y' => 16, 'image' => 'photo-1580763850690-44fd66eb2863',
                'desc' => 'Open air on the Agusan side of the compound. Fastest surface we have and the coolest place to play after four in the afternoon.',
            ],
            [
                'code' => 'Court 5', 'name' => 'Sunset Court', 'slug' => 'sunset-court',
                'surface_type' => 'outdoor', 'surface' => 'Post-tension concrete', 'hourly_rate' => 150, 'peak_rate' => 200,
                'map_x' => 14, 'map_y' => 16, 'image' => 'photo-1515017804404-92b19fdfe6ac',
                'desc' => 'West-facing and lit until 22:00. Bring a cap for the 17:00 slot — the glare off the far baseline is real for about twenty minutes.',
            ],
        ];

        $courtRows = [];
        foreach ($courts as $i => $c) {
            $courtRows[] = [
                'code'         => $c['code'],
                'name'         => $c['name'],
                'slug'         => $c['slug'],
                'surface_type' => $c['surface_type'],
                'surface'      => $c['surface'],
                'status'       => 'active',
                'hourly_rate'  => $c['hourly_rate'],
                'peak_rate'    => $c['peak_rate'],
                'capacity'     => 4,
                'has_lights'   => 1,
                'description'  => $c['desc'],
                'image_path'   => $c['image'],
                'map_x'        => $c['map_x'],
                'map_y'        => $c['map_y'],
                'sort_order'   => $i,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }
        $this->db->table('courts')->insertBatch($courtRows);

        // ------------------------------------------------------- operating hours
        $courtIds = array_column($this->db->table('courts')->select('id')->orderBy('sort_order')->get()->getResultArray(), 'id');
        $schedules = [];
        foreach ($courtIds as $courtId) {
            for ($day = 0; $day <= 6; $day++) {
                $schedules[] = [
                    'court_id'    => $courtId,
                    'day_of_week' => $day,
                    'opens_at'    => '05:00:00',
                    'closes_at'   => '05:00:00',
                    'is_closed'   => 0,
                ];
            }
        }
        $this->db->table('court_schedules')->insertBatch($schedules);

        // ------------------------------------------------------------- equipment
        $equipment = [
            ['EQ-PAD-01', 'Graphite Paddle — Standard', 'paddle', 'Mid-weight graphite face, cushioned grip. Sized for adults.', 80, 500, 24, 24, 'photo-1693142517898-2f986215e412'],
            ['EQ-PAD-02', 'Carbon Paddle — Performance', 'paddle', 'Raw carbon face with a longer handle. What the coaches use.', 150, 1000, 8, 8, 'photo-1710772099352-f8fbb7b30977'],
            ['EQ-PAD-03', 'Junior Paddle', 'paddle', 'Shorter handle, lighter head. Ages 7-13.', 60, 300, 10, 10, 'photo-1642104798671-01a4129f4fdc'],
            ['EQ-BAL-01', 'Outdoor Ball Set (3)', 'ball', 'Franklin X-40 equivalent, 40-hole outdoor. Three per set.', 50, 0, 40, 40, 'photo-1711996151738-657bd95b7cdb'],
            ['EQ-BAL-02', 'Indoor Ball Set (3)', 'ball', '26-hole indoor ball for the covered courts.', 50, 0, 30, 30, 'photo-1541744573515-478c959628a0'],
            ['EQ-NET-01', 'Portable Net System', 'net', 'Regulation portable net, wheeled frame. For overflow play.', 250, 1500, 2, 2, 'photo-1580763850522-504d40a05c50'],
            ['EQ-SHO-01', 'Court Shoes (loan)', 'shoes', 'Non-marking court shoes, sizes 6-11. Required on covered courts.', 100, 500, 16, 16, 'photo-1737476997205-b3336182f215'],
        ];

        $eqRows = [];
        foreach ($equipment as [$code, $name, $cat, $desc, $rate, $deposit, $total, $avail, $img]) {
            $eqRows[] = [
                'code' => $code, 'name' => $name, 'category' => $cat, 'description' => $desc,
                'rental_rate_hourly' => $rate, 'deposit' => $deposit,
                'total_qty' => $total, 'available_qty' => $avail,
                'condition_grade' => 'good', 'image_path' => $img, 'is_active' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        $this->db->table('equipment')->insertBatch($eqRows);

        // -------------------------------------------------------------- products
        $products = [
            ['BP-PAD-100', 'Bernie Signature Carbon Paddle', 'bernie-signature-carbon-paddle', 'paddles', 'Bernie Pickleball', 6450, 4100, 14, 'photo-1693142518820-78d7a05f1546', 1,
                'Raw T700 carbon face, 16mm polymer core, 5.25" handle. Built for the third-shot drop game we teach here. Comes with a Bernie cover.'],
            ['BP-PAD-101', 'Trento Control 16', 'trento-control-16', 'paddles', 'Bernie Pickleball', 4200, 2700, 22, 'photo-1710772099352-f8fbb7b30977', 1,
                'The club paddle. Forgiving sweet spot, quiet face, and the one we hand beginners who decide to stop renting.'],
            ['BP-BAL-200', 'Outdoor Balls — Tube of 6', 'outdoor-balls-tube-6', 'balls', 'Franklin', 890, 560, 48, 'photo-1711996151738-657bd95b7cdb', 0,
                'The 40-hole outdoor ball used on Court 4 and Court 5. Tournament approved.'],
            ['BP-BAL-201', 'Indoor Balls — Tube of 6', 'indoor-balls-tube-6', 'balls', 'Onix', 780, 480, 36, 'photo-1541744573515-478c959628a0', 0,
                '26-hole indoor ball. Softer, slower, and what the covered courts are set up for.'],
            ['BP-APP-300', 'Bernie Court Tee', 'bernie-court-tee', 'apparel', 'Bernie Pickleball', 750, 380, 60, 'photo-1734161081396-0f0572a16bf6', 1,
                'Moisture-wicking, navy with the court-map graphic on the back. Sizes S-XXL.'],
            ['BP-APP-301', 'Kitchen Line Cap', 'kitchen-line-cap', 'apparel', 'Bernie Pickleball', 550, 270, 44, 'photo-1737476997205-b3336182f215', 0,
                'Six-panel, low crown, electric blue underbrim. For the 17:00 slot on Sunset Court.'],
            ['BP-BAG-400', 'Tour Paddle Bag', 'tour-paddle-bag', 'bags', 'Selkirk', 3850, 2400, 9, 'photo-1723004714201-cf224222b897', 0,
                'Holds four paddles, a fence hook, and a vented shoe compartment.'],
            ['BP-ACC-500', 'Overgrip 3-Pack', 'overgrip-3-pack', 'accessories', 'Tourna', 380, 190, 80, 'photo-1642104798671-01a4129f4fdc', 0,
                'Tacky dry grip. The only overgrip that survives an Agusan afternoon.'],
            ['BP-DRK-600', 'Electrolyte Bottle 500ml', 'electrolyte-bottle-500ml', 'drinks', 'Pocari', 65, 42, 120, 'photo-1687102618656-907b757ad5d9', 0,
                'Cold, at the desk. You will need it.'],
        ];

        $prodRows = [];
        foreach ($products as [$sku, $name, $slug, $cat, $brand, $price, $cost, $stock, $img, $featured, $desc]) {
            $prodRows[] = [
                'sku' => $sku, 'name' => $name, 'slug' => $slug, 'category' => $cat, 'brand' => $brand,
                'description' => $desc, 'price' => $price, 'cost_price' => $cost, 'stock_qty' => $stock,
                'reorder_level' => 6, 'image_path' => $img, 'is_featured' => $featured, 'is_active' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        $this->db->table('products')->insertBatch($prodRows);

        // ----------------------------------------------------------- maintenance
        $staffId = $this->db->table('users')->select('id')->where('email', 'facilities@berniepickleball.ph')->get()->getRow('id');
        $mgrId   = $this->db->table('users')->select('id')->where('email', 'maricel@berniepickleball.ph')->get()->getRow('id');

        $maint = [
            [$courtIds[3], 'Resurface east baseline', 'resurfacing', '+9 days 07:00', '+9 days 12:00', 'scheduled', 18500,
                'Hairline cracking along the east baseline after the last rainy season. Grind, patch, two coats.'],
            [$courtIds[0], 'Quarterly LED array inspection', 'inspection', '+3 days 06:00', '+3 days 08:00', 'scheduled', 2500,
                'Check all eight poles, driver temps and the lux reading at centre net. Report to manager.'],
            [$courtIds[4], 'Net post re-tension', 'repair', '-2 days 06:00', '-2 days 07:30', 'completed', 800,
                'North post had drifted about 4cm. Re-plumbed and re-tensioned to 30 inches at centre.'],
            [$courtIds[1], 'Deep clean and line repaint', 'cleaning', '+16 days 06:00', '+16 days 11:00', 'scheduled', 5200,
                'Pressure wash, then repaint the kitchen line — it is ghosting under the canopy lights.'],
        ];

        $maintRows = [];
        foreach ($maint as [$courtId, $title, $type, $start, $end, $status, $cost, $desc]) {
            $maintRows[] = [
                'court_id' => $courtId, 'title' => $title, 'description' => $desc, 'type' => $type,
                'scheduled_start' => date('Y-m-d H:i:s', strtotime($start)),
                'scheduled_end'   => date('Y-m-d H:i:s', strtotime($end)),
                'status' => $status, 'assigned_to' => $staffId, 'cost' => $cost,
                'created_by' => $mgrId, 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        $this->db->table('court_maintenance')->insertBatch($maintRows);
    }
}
