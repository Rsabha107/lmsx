<?php

namespace Database\Seeders;

use App\Models\Airport;
use App\Models\Event;
use App\Models\Team;
use App\Models\TeamFlight;
use Illuminate\Database\Seeder;

/**
 * Seeds inbound/outbound team flights from
 * docs/u17 teams arrival and departure.pdf for the FIFA U-17 World Cup
 * Qatar 2026 event. Depends on U17EventTeamsSeeder having already
 * created the teams.
 */
class U17EventTeamFlightsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $event = Event::where('short_name', 'FU17WC')
            ->orWhere('name', 'FIFA U-17 WORLD CUP QATAR 2026')
            ->first();

        if (! $event) {
            $this->command?->warn('FIFA U-17 World Cup Qatar 2026 event not found, skipping.');

            return;
        }

        $doha = Airport::firstOrCreate(
            ['code' => 'DOH'],
            ['name' => 'Hamad International Airport', 'city' => 'Doha', 'country' => 'Qatar']
        );

        // code, inbound flight #, arrival date, arrival time, inbound planned bags,
        // outbound flight #, last match status, last match date, departure date, departure time
        $flights = [
            ['ARG-17', 'TK 782', '2025-10-29', '05:45', 80, 'TK781', 'Eliminated', '2025-11-14', '2025-11-17', '01:50'],
            ['AUT-17', 'QR 190', '2025-10-28', '16:25', 105, 'QR 199', 'Eliminated', '2025-11-27', '2025-11-29', '09:35'],
            ['BEL-17', 'QR 194', '2025-10-30', '23:05', 48, 'QR 115', 'Eliminated', '2025-11-14', '2025-11-15', '02:50'],
            ['BFA-17', 'QR 1023', '2025-11-01', '13:25', 40, 'WY662', 'Eliminated', '2025-11-21', '2025-11-22', '21:50'],
            ['BOL-17', 'QR 1045', '2025-10-30', '12:30', 35, 'QR 785', 'Eliminated', '2025-11-09', '2025-11-12', '00:50'],
            ['BRA-17', 'QR 780', '2025-10-29', '16:05', 97, 'QR 779', 'Eliminated', '2025-11-27', '2025-11-29', '01:30'],
            ['CAN-17', 'QR 1039', '2025-11-01', '11:35', 52, 'QR 767', 'Eliminated', '2025-11-14', '2025-11-16', '08:30'],
            ['CHI-17', 'FZ 1', '2025-11-01', '09:05', 42, 'QR151', 'Eliminated', '2025-11-11', '2025-11-12', '15:20'],
            ['CIV-17', 'QR 1015', '2025-10-30', '22:40', 38, 'QR1423', 'Eliminated', '2025-11-10', '2025-11-12', '09:05'],
            ['COL-17', 'TK780', '2025-10-31', '00:50', 48, 'TK 781', 'Eliminated', '2025-11-14', '2025-11-16', '01:50'],
            ['CRC-17', 'QR 150', '2025-10-28', '23:55', 94, 'QR 147', 'Eliminated', '2025-11-09', '2025-11-14', '01:00'],
            ['CRO-17', 'QR 218', '2025-10-29', '22:10', 60, 'QR 215', 'Eliminated', '2025-11-15', '2025-11-18', '02:30'],
            ['CZE-17', 'QR 292', '2025-11-01', '22:40', 46, 'QR289', 'Eliminated', '2025-11-15', '2025-11-17', '02:10'],
            ['EGY-17', 'BUS', '2025-10-30', null, 48, 'QR1301', 'Eliminated', '2025-11-14', '2025-11-16', '14:45'],
            ['ENG-17', 'QR 010', '2025-10-30', '18:15', 70, 'QR015', 'Eliminated', '2025-11-18', '2025-11-19', '15:15'],
            ['FIJ-17', 'BUS', '2025-10-30', null, 36, 'QR 818', 'Eliminated', '2025-11-09', '2025-11-11', '02:05'],
            ['FRA-17', 'QR 040', '2025-11-01', '23:35', 52, 'QR039', 'Eliminated', '2025-11-18', '2025-11-19', '08:05'],
            ['GER-17', 'QR 1039', '2025-10-31', '11:35', 60, 'QR067', 'Eliminated', '2025-11-15', '2025-11-17', '08:50'],
            ['HAI-17', 'QR 146', '2025-10-31', '23:20', 50, 'QR329', 'Eliminated', '2025-11-10', '2025-11-12', '01:35'],
            ['HON-17', 'TK 780', '2025-10-31', '00:45', 42, 'TK 783', 'Eliminated', '2025-11-10', '2025-11-12', '06:45'],
            ['IDN-17', 'QR 1023', '2025-10-31', '13:30', 115, 'QR958', 'Eliminated', '2025-11-10', '2025-11-12', '18:35'],
            ['IRL-17', 'QR 018', '2025-10-31', '23:55', 30, 'QR 019', 'Eliminated', '2025-11-18', '2025-11-20', '01:20'],
            ['ITA-17', 'QR124', '2025-10-30', '16:40', 70, 'QR 127', 'Eliminated', '2025-11-27', '2025-11-28', '08:50'],
            ['JPN-17', 'QR 1039', '2025-10-30', '11:30', 35, 'QR 806', 'Eliminated', '2025-11-21', '2025-11-23', '01:55'],
            ['KOR-17', 'QR 1007', '2025-10-31', '12:10', 48, 'QR 858', 'Eliminated', '2025-11-15', '2025-11-17', '02:30'],
            ['KSA-17', 'QR 1007', '2025-11-01', '12:10', 48, 'SV543', 'Eliminated', '2025-11-11', '2025-11-12', '08:00'],
            ['MAR-17', 'QR 1039', '2025-10-30', '11:35', 63, 'QR 4567', 'Eliminated', '2025-11-21', '2025-11-24', '01:10'],
            ['MEX-17', 'QR 1039', '2025-10-31', '11:35', 138, 'QR 149', 'Eliminated', '2025-11-18', '2025-11-20', '08:15'],
            ['MLI-17', 'QR 1051', '2025-11-01', '18:05', 48, 'ET433', 'Eliminated', '2025-11-18', '2025-11-19', '03:15'],
            ['NCL-17', 'FZ 017', '2025-10-30', '16:10', 70, 'QR942', 'Eliminated', '2025-11-09', '2025-11-10', '19:55'],
            ['NZL-17', 'EY 661', '2025-11-01', '09:35', 71, 'QR920', 'Eliminated', '2025-11-11', '2025-11-12', '18:15'],
            ['PAN-17', 'FZ 003', '2025-11-01', '10:15', 52, 'TK 783', 'Eliminated', '2025-11-11', '2025-11-12', '06:45'],
            ['PAR-17', 'QR 1007', '2025-11-01', '12:10', 48, 'QR773', 'Eliminated', '2025-11-14', '2025-11-16', '07:45'],
            ['POR-17', 'QR 342', '2025-10-31', '00:40', 73, 'QR 341', 'Champions', '2025-11-27', '2025-11-29', '07:40'],
            ['PRK-17', 'QR 893', '2025-10-29', '05:55', 31, 'QR 892', 'Eliminated', '2025-11-18', '2025-11-20', '01:55'],
            ['QAT-17', 'BUS', '2025-10-30', null, null, 'BUS', 'Eliminated', '2025-11-09', '2025-11-11', null],
            ['RSA-17', 'QR 1364', '2025-10-30', '23:15', 48, 'QR1377', 'Eliminated', '2025-11-15', '2025-11-17', '08:05'],
            ['SEN-17', 'TK 780', '2025-10-30', '00:45', 50, 'TK 857', 'Eliminated', '2025-11-15', '2025-11-18', '06:45'],
            ['SLV-17', 'QR 152', '2025-10-31', '07:00', 59, 'QR147', 'Eliminated', '2025-11-10', '2025-11-11', '01:00'],
            ['SUI-17', 'QR 1007', '2025-10-31', '12:10', 110, 'QR099', 'Eliminated', '2025-11-21', '2025-11-22', '09:05'],
            ['TJK-17', 'FZ 017', '2025-11-01', '16:10', 40, 'FZ 010', 'Eliminated', '2025-11-11', '2025-11-12', '19:00'],
            ['TUN-17', 'QR 1063', '2025-10-30', '14:30', 80, 'QR1399', 'Eliminated', '2025-11-15', '2025-11-16', '09:05'],
            ['UAE-17', 'QR 1007', '2025-10-30', '12:10', 48, 'QR 1036', 'Eliminated', '2025-11-09', '2025-11-10', '14:00'],
            ['UGA-17', 'QR 1003', '2025-11-01', '05:35', 48, 'QR1383', 'Eliminated', '2025-11-18', '2025-11-21', '10:05'],
            ['USA-17', 'QR 1051', '2025-11-01', '18:05', 48, 'QR 755', 'Eliminated', '2025-11-14', '2025-11-16', '08:10'],
            ['UZB-17', 'QR 1039', '2025-11-01', '11:35', 56, 'QR377', 'Eliminated', '2025-11-18', '2025-11-19', '20:15'],
            ['VEN-17', 'QR 1023', '2025-10-31', '13:30', 97, 'IB 392', 'Eliminated', '2025-11-15', '2025-11-17', '02:10'],
            ['ZAM-17', 'FZ 003', '2025-10-31', '10:15', 48, 'QR 1341', 'Eliminated', '2025-11-14', '2025-11-15', '18:55'],
        ];

        $teamIdsByCode = Team::where('event_id', $event->id)
            ->whereIn('code', array_column($flights, '0'))
            ->pluck('id', 'code');

        foreach ($flights as [$code, $inFlight, $arrDate, $arrTime, $bags, $outFlight, $lastStatus, $lastMatchDate, $depDate, $depTime]) {
            $teamId = $teamIdsByCode[$code] ?? null;

            if (! $teamId) {
                $this->command?->warn("Team {$code} not found, skipping flights.");

                continue;
            }

            $isInboundBus = $inFlight === 'BUS';
            $isOutboundBus = $outFlight === 'BUS';

            TeamFlight::updateOrCreate(
                ['event_id' => $event->id, 'team_id' => $teamId, 'direction' => 'arrival'],
                [
                    'flight_number' => $inFlight,
                    'origin_airport_id' => null,
                    'destination_airport_id' => $isInboundBus ? null : $doha->id,
                    'scheduled_at' => "{$arrDate} " . ($arrTime ?? '00:00') . ':00',
                    'planned_bags' => $bags,
                    'party_size_total' => null,
                ]
            );

            TeamFlight::updateOrCreate(
                ['event_id' => $event->id, 'team_id' => $teamId, 'direction' => 'departure'],
                [
                    'flight_number' => $outFlight,
                    'origin_airport_id' => $isOutboundBus ? null : $doha->id,
                    'destination_airport_id' => null,
                    'scheduled_at' => "{$depDate} " . ($depTime ?? '00:00') . ':00',
                    'notes' => "Last match: {$lastStatus} ({$lastMatchDate})",
                ]
            );
        }
    }
}
