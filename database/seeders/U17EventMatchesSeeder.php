<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\Venue;
use Illuminate\Database\Seeder;

/**
 * Seeds the 104-match schedule (group stage through final) from
 * docs/u17 event teams matches.pdf for the FIFA U-17 World Cup Qatar 2026 event.
 */
class U17EventMatchesSeeder extends Seeder
{
    public function run(): void
    {
        $event = Event::where('short_name', 'FU17WC')
            ->orWhere('name', 'FIFA U-17 WORLD CUP QATAR 2026')
            ->first();

        if (! $event) {
            $this->command?->warn('FIFA U-17 World Cup Qatar 2026 event not found, skipping.');

            return;
        }

        $pitches = range(1, 9);
        foreach ($pitches as $pitch) {
            Venue::firstOrCreate(['name' => "ASPIRE ZONE (PITCH {$pitch})"]);
        }
        Venue::firstOrCreate(['name' => 'KHALIFA INTERNATIONAL STADIUM']);

        $teamIdsByCode = Team::where('event_id', $event->id)->pluck('id', 'code');
        $venueIdsByName = Venue::pluck('id', 'name');

        // number, date, kick_off, team1, team2, venue, stage
        $matches = [
            ['FU17-001', '2025-11-03', '18:45', 'QAT-17', 'ITA-17', 'ASPIRE ZONE (PITCH 7)', 'MD-R1'],
            ['FU17-002', '2025-11-03', '15:30', 'RSA-17', 'BOL-17', 'ASPIRE ZONE (PITCH 3)', 'MD-R1'],
            ['FU17-003', '2025-11-03', '16:30', 'JPN-17', 'MAR-17', 'ASPIRE ZONE (PITCH 5)', 'MD-R1'],
            ['FU17-004', '2025-11-03', '18:15', 'NCL-17', 'POR-17', 'ASPIRE ZONE (PITCH 4)', 'MD-R1'],
            ['FU17-005', '2025-11-03', '16:00', 'SEN-17', 'CRO-17', 'ASPIRE ZONE (PITCH 1)', 'MD-R1'],
            ['FU17-006', '2025-11-03', '15:30', 'CRC-17', 'UAE-17', 'ASPIRE ZONE (PITCH 8)', 'MD-R1'],
            ['FU17-007', '2025-11-03', '17:45', 'ARG-17', 'BEL-17', 'ASPIRE ZONE (PITCH 2)', 'MD-R1'],
            ['FU17-008', '2025-11-03', '18:45', 'TUN-17', 'FIJ-17', 'ASPIRE ZONE (PITCH 9)', 'MD-R1'],
            ['FU17-009', '2025-11-04', '18:15', 'ENG-17', 'VEN-17', 'ASPIRE ZONE (PITCH 4)', 'MD-R1'],
            ['FU17-010', '2025-11-04', '16:30', 'HAI-17', 'EGY-17', 'ASPIRE ZONE (PITCH 5)', 'MD-R1'],
            ['FU17-011', '2025-11-04', '16:00', 'MEX-17', 'KOR-17', 'ASPIRE ZONE (PITCH 1)', 'MD-R1'],
            ['FU17-012', '2025-11-04', '15:30', 'CIV-17', 'SUI-17', 'ASPIRE ZONE (PITCH 8)', 'MD-R1'],
            ['FU17-013', '2025-11-04', '17:45', 'GER-17', 'COL-17', 'ASPIRE ZONE (PITCH 2)', 'MD-R1'],
            ['FU17-014', '2025-11-04', '18:45', 'PRK-17', 'SLV-17', 'ASPIRE ZONE (PITCH 9)', 'MD-R1'],
            ['FU17-015', '2025-11-04', '15:30', 'BRA-17', 'HON-17', 'ASPIRE ZONE (PITCH 3)', 'MD-R1'],
            ['FU17-016', '2025-11-04', '18:45', 'IDN-17', 'ZAM-17', 'ASPIRE ZONE (PITCH 7)', 'MD-R1'],
            ['FU17-017', '2025-11-05', '18:15', 'USA-17', 'BFA-17', 'ASPIRE ZONE (PITCH 4)', 'MD-R1'],
            ['FU17-018', '2025-11-05', '15:30', 'TJK-17', 'CZE-17', 'ASPIRE ZONE (PITCH 8)', 'MD-R1'],
            ['FU17-019', '2025-11-05', '16:00', 'PAR-17', 'UZB-17', 'ASPIRE ZONE (PITCH 1)', 'MD-R1'],
            ['FU17-020', '2025-11-05', '15:30', 'PAN-17', 'IRL-17', 'ASPIRE ZONE (PITCH 3)', 'MD-R1'],
            ['FU17-021', '2025-11-05', '18:45', 'FRA-17', 'CHI-17', 'ASPIRE ZONE (PITCH 7)', 'MD-R1'],
            ['FU17-022', '2025-11-05', '18:45', 'CAN-17', 'UGA-17', 'ASPIRE ZONE (PITCH 9)', 'MD-R1'],
            ['FU17-023', '2025-11-05', '17:45', 'MLI-17', 'NZL-17', 'ASPIRE ZONE (PITCH 2)', 'MD-R1'],
            ['FU17-024', '2025-11-05', '16:30', 'AUT-17', 'KSA-17', 'ASPIRE ZONE (PITCH 5)', 'MD-R1'],

            ['FU17-025', '2025-11-06', '18:45', 'QAT-17', 'RSA-17', 'ASPIRE ZONE (PITCH 7)', 'MD-R2'],
            ['FU17-026', '2025-11-06', '15:30', 'BOL-17', 'ITA-17', 'ASPIRE ZONE (PITCH 3)', 'MD-R2'],
            ['FU17-027', '2025-11-06', '16:00', 'JPN-17', 'NCL-17', 'ASPIRE ZONE (PITCH 1)', 'MD-R2'],
            ['FU17-028', '2025-11-06', '15:30', 'POR-17', 'MAR-17', 'ASPIRE ZONE (PITCH 8)', 'MD-R2'],
            ['FU17-029', '2025-11-06', '18:45', 'SEN-17', 'CRC-17', 'ASPIRE ZONE (PITCH 9)', 'MD-R2'],
            ['FU17-030', '2025-11-06', '18:15', 'UAE-17', 'CRO-17', 'ASPIRE ZONE (PITCH 4)', 'MD-R2'],
            ['FU17-031', '2025-11-06', '16:30', 'ARG-17', 'TUN-17', 'ASPIRE ZONE (PITCH 5)', 'MD-R2'],
            ['FU17-032', '2025-11-06', '17:45', 'FIJ-17', 'BEL-17', 'ASPIRE ZONE (PITCH 2)', 'MD-R2'],
            ['FU17-033', '2025-11-07', '15:30', 'ENG-17', 'HAI-17', 'ASPIRE ZONE (PITCH 3)', 'MD-R2'],
            ['FU17-034', '2025-11-07', '16:30', 'EGY-17', 'VEN-17', 'ASPIRE ZONE (PITCH 5)', 'MD-R2'],
            ['FU17-035', '2025-11-07', '17:45', 'MEX-17', 'CIV-17', 'ASPIRE ZONE (PITCH 2)', 'MD-R2'],
            ['FU17-036', '2025-11-07', '18:15', 'SUI-17', 'KOR-17', 'ASPIRE ZONE (PITCH 4)', 'MD-R2'],
            ['FU17-037', '2025-11-07', '16:00', 'GER-17', 'PRK-17', 'ASPIRE ZONE (PITCH 1)', 'MD-R2'],
            ['FU17-038', '2025-11-07', '15:30', 'SLV-17', 'COL-17', 'ASPIRE ZONE (PITCH 8)', 'MD-R2'],
            ['FU17-039', '2025-11-07', '18:45', 'BRA-17', 'IDN-17', 'ASPIRE ZONE (PITCH 7)', 'MD-R2'],
            ['FU17-040', '2025-11-07', '18:45', 'ZAM-17', 'HON-17', 'ASPIRE ZONE (PITCH 9)', 'MD-R2'],
            ['FU17-041', '2025-11-08', '17:45', 'USA-17', 'TJK-17', 'ASPIRE ZONE (PITCH 2)', 'MD-R2'],
            ['FU17-042', '2025-11-08', '15:30', 'CZE-17', 'BFA-17', 'ASPIRE ZONE (PITCH 3)', 'MD-R2'],
            ['FU17-043', '2025-11-08', '18:15', 'PAR-17', 'PAN-17', 'ASPIRE ZONE (PITCH 4)', 'MD-R2'],
            ['FU17-044', '2025-11-08', '18:45', 'IRL-17', 'UZB-17', 'ASPIRE ZONE (PITCH 9)', 'MD-R2'],
            ['FU17-045', '2025-11-08', '16:30', 'FRA-17', 'CAN-17', 'ASPIRE ZONE (PITCH 5)', 'MD-R2'],
            ['FU17-046', '2025-11-08', '15:30', 'UGA-17', 'CHI-17', 'ASPIRE ZONE (PITCH 8)', 'MD-R2'],
            ['FU17-047', '2025-11-08', '16:00', 'MLI-17', 'AUT-17', 'ASPIRE ZONE (PITCH 1)', 'MD-R2'],
            ['FU17-048', '2025-11-08', '18:45', 'KSA-17', 'NZL-17', 'ASPIRE ZONE (PITCH 7)', 'MD-R2'],

            ['FU17-049', '2025-11-09', '18:45', 'BOL-17', 'QAT-17', 'ASPIRE ZONE (PITCH 7)', 'MD-R3'],
            ['FU17-050', '2025-11-09', '18:45', 'ITA-17', 'RSA-17', 'ASPIRE ZONE (PITCH 9)', 'MD-R3'],
            ['FU17-051', '2025-11-09', '16:30', 'POR-17', 'JPN-17', 'ASPIRE ZONE (PITCH 5)', 'MD-R3'],
            ['FU17-052', '2025-11-09', '16:30', 'MAR-17', 'NCL-17', 'ASPIRE ZONE (PITCH 1)', 'MD-R3'],
            ['FU17-053', '2025-11-09', '17:45', 'UAE-17', 'SEN-17', 'ASPIRE ZONE (PITCH 2)', 'MD-R3'],
            ['FU17-054', '2025-11-09', '17:45', 'CRO-17', 'CRC-17', 'ASPIRE ZONE (PITCH 4)', 'MD-R3'],
            ['FU17-055', '2025-11-09', '15:30', 'FIJ-17', 'ARG-17', 'ASPIRE ZONE (PITCH 3)', 'MD-R3'],
            ['FU17-056', '2025-11-09', '15:30', 'BEL-17', 'TUN-17', 'ASPIRE ZONE (PITCH 8)', 'MD-R3'],
            ['FU17-057', '2025-11-10', '18:45', 'EGY-17', 'ENG-17', 'ASPIRE ZONE (PITCH 7)', 'MD-R3'],
            ['FU17-058', '2025-11-10', '18:45', 'VEN-17', 'HAI-17', 'ASPIRE ZONE (PITCH 9)', 'MD-R3'],
            ['FU17-059', '2025-11-10', '15:30', 'SUI-17', 'MEX-17', 'ASPIRE ZONE (PITCH 3)', 'MD-R3'],
            ['FU17-060', '2025-11-10', '15:30', 'KOR-17', 'CIV-17', 'ASPIRE ZONE (PITCH 8)', 'MD-R3'],
            ['FU17-061', '2025-11-10', '16:30', 'SLV-17', 'GER-17', 'ASPIRE ZONE (PITCH 5)', 'MD-R3'],
            ['FU17-062', '2025-11-10', '16:30', 'COL-17', 'PRK-17', 'ASPIRE ZONE (PITCH 1)', 'MD-R3'],
            ['FU17-063', '2025-11-10', '17:45', 'ZAM-17', 'BRA-17', 'ASPIRE ZONE (PITCH 4)', 'MD-R3'],
            ['FU17-064', '2025-11-10', '17:45', 'HON-17', 'IDN-17', 'ASPIRE ZONE (PITCH 2)', 'MD-R3'],
            ['FU17-065', '2025-11-11', '17:45', 'CZE-17', 'USA-17', 'ASPIRE ZONE (PITCH 4)', 'MD-R3'],
            ['FU17-066', '2025-11-11', '17:45', 'BFA-17', 'TJK-17', 'ASPIRE ZONE (PITCH 2)', 'MD-R3'],
            ['FU17-067', '2025-11-11', '16:30', 'IRL-17', 'PAR-17', 'ASPIRE ZONE (PITCH 5)', 'MD-R3'],
            ['FU17-068', '2025-11-11', '16:30', 'UZB-17', 'PAN-17', 'ASPIRE ZONE (PITCH 1)', 'MD-R3'],
            ['FU17-069', '2025-11-11', '15:30', 'UGA-17', 'FRA-17', 'ASPIRE ZONE (PITCH 3)', 'MD-R3'],
            ['FU17-070', '2025-11-11', '15:30', 'CHI-17', 'CAN-17', 'ASPIRE ZONE (PITCH 8)', 'MD-R3'],
            ['FU17-071', '2025-11-11', '18:45', 'KSA-17', 'MLI-17', 'ASPIRE ZONE (PITCH 7)', 'MD-R3'],
            ['FU17-072', '2025-11-11', '18:45', 'NZL-17', 'AUT-17', 'ASPIRE ZONE (PITCH 9)', 'MD-R3'],

            ['FU17-073', '2025-11-14', '17:45', 'ARG-17', 'MEX-17', 'ASPIRE ZONE (PITCH 2)', 'R32'],
            ['FU17-074', '2025-11-15', '18:45', 'AUT-17', 'TUN-17', 'ASPIRE ZONE (PITCH 5)', 'R32'],
            ['FU17-075', '2025-11-15', '16:00', 'ITA-17', 'CZE-17', 'ASPIRE ZONE (PITCH 1)', 'R32'],
            ['FU17-076', '2025-11-14', '18:45', 'USA-17', 'MAR-17', 'ASPIRE ZONE (PITCH 7)', 'R32'],
            ['FU17-077', '2025-11-14', '18:45', 'BRA-17', 'PAR-17', 'ASPIRE ZONE (PITCH 9)', 'R32'],
            ['FU17-078', '2025-11-15', '15:30', 'SEN-17', 'UGA-17', 'ASPIRE ZONE (PITCH 7)', 'R32'],
            ['FU17-079', '2025-11-15', '18:15', 'VEN-17', 'PRK-17', 'ASPIRE ZONE (PITCH 4)', 'R32'],
            ['FU17-080', '2025-11-14', '16:00', 'SUI-17', 'EGY-17', 'ASPIRE ZONE (PITCH 5)', 'R32'],
            ['FU17-081', '2025-11-14', '18:15', 'IRL-17', 'CAN-17', 'ASPIRE ZONE (PITCH 4)', 'R32'],
            ['FU17-082', '2025-11-15', '16:30', 'JPN-17', 'RSA-17', 'ASPIRE ZONE (PITCH 3)', 'R32'],
            ['FU17-083', '2025-11-15', '17:45', 'GER-17', 'BFA-17', 'ASPIRE ZONE (PITCH 2)', 'R32'],
            ['FU17-084', '2025-11-14', '16:30', 'FRA-17', 'COL-17', 'ASPIRE ZONE (PITCH 1)', 'R32'],
            ['FU17-085', '2025-11-14', '15:30', 'ZAM-17', 'MLI-17', 'ASPIRE ZONE (PITCH 8)', 'R32'],
            ['FU17-086', '2025-11-15', '18:45', 'CRO-17', 'UZB-17', 'ASPIRE ZONE (PITCH 9)', 'R32'],
            ['FU17-087', '2025-11-15', '15:30', 'KOR-17', 'ENG-17', 'ASPIRE ZONE (PITCH 8)', 'R32'],
            ['FU17-088', '2025-11-14', '15:30', 'POR-17', 'BEL-17', 'ASPIRE ZONE (PITCH 3)', 'R32'],

            ['FU17-089', '2025-11-18', '16:00', 'MEX-17', 'POR-17', 'ASPIRE ZONE (PITCH 3)', 'R16'],
            ['FU17-090', '2025-11-18', '18:45', 'AUT-17', 'ENG-17', 'ASPIRE ZONE (PITCH 9)', 'R16'],
            ['FU17-091', '2025-11-18', '15:30', 'ITA-17', 'UZB-17', 'ASPIRE ZONE (PITCH 8)', 'R16'],
            ['FU17-092', '2025-11-18', '18:45', 'MAR-17', 'MLI-17', 'ASPIRE ZONE (PITCH 7)', 'R16'],
            ['FU17-093', '2025-11-18', '16:30', 'BRA-17', 'FRA-17', 'ASPIRE ZONE (PITCH 2)', 'R16'],
            ['FU17-094', '2025-11-18', '15:30', 'UGA-17', 'BFA-17', 'ASPIRE ZONE (PITCH 5)', 'R16'],
            ['FU17-095', '2025-11-18', '18:15', 'PRK-17', 'JPN-17', 'ASPIRE ZONE (PITCH 4)', 'R16'],
            ['FU17-096', '2025-11-18', '17:45', 'SUI-17', 'IRL-17', 'ASPIRE ZONE (PITCH 1)', 'R16'],

            ['FU17-097', '2025-11-21', '17:45', 'POR-17', 'SUI-17', 'ASPIRE ZONE (PITCH 2)', 'QF'],
            ['FU17-098', '2025-11-21', '15:30', 'AUT-17', 'JPN-17', 'ASPIRE ZONE (PITCH 3)', 'QF'],
            ['FU17-099', '2025-11-21', '16:30', 'ITA-17', 'BFA-17', 'ASPIRE ZONE (PITCH 5)', 'QF'],
            ['FU17-100', '2025-11-21', '18:45', 'MAR-17', 'BRA-17', 'ASPIRE ZONE (PITCH 7)', 'QF'],

            ['FU17-101', '2025-11-24', '19:00', 'POR-17', 'BRA-17', 'ASPIRE ZONE (PITCH 7)', 'SF'],
            ['FU17-102', '2025-11-24', '16:30', 'AUT-17', 'ITA-17', 'ASPIRE ZONE (PITCH 5)', 'SF'],

            ['FU17-103', '2025-11-27', '15:30', 'ITA-17', 'BRA-17', 'ASPIRE ZONE (PITCH 7)', '3PL'],

            ['FU17-104', '2025-11-27', '19:00', 'POR-17', 'AUT-17', 'KHALIFA INTERNATIONAL STADIUM', 'F'],
        ];

        foreach ($matches as [$number, $date, $ko, $team1Code, $team2Code, $venueName, $stage]) {
            $team1Id = $teamIdsByCode[$team1Code] ?? null;
            $team2Id = $teamIdsByCode[$team2Code] ?? null;
            $venueId = $venueIdsByName[$venueName] ?? null;

            if (! $team1Id || ! $team2Id) {
                $this->command?->warn("Match {$number}: team {$team1Code} or {$team2Code} not found, skipping.");

                continue;
            }

            GameMatch::updateOrCreate(
                ['match_number' => $number],
                [
                    'event_id' => $event->id,
                    'venue_id' => $venueId,
                    'team1_id' => $team1Id,
                    'team2_id' => $team2Id,
                    'stage' => $stage,
                    'match_date' => $date,
                    'kick_off' => "{$date} {$ko}:00",
                ]
            );
        }
    }
}
