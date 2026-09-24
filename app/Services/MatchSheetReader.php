<?php

namespace App\Services;

/**
 * Reads a fixtures sheet into rows keyed as MatchImportService expects.
 */
class MatchSheetReader extends AbstractSheetReader
{
    /**
     * Field aliases, keyed by normalized header text (letters/digits only, lowercased).
     */
    protected const HEADER_ALIASES = [
        // My own template headers.
        'matchnumber' => 'match_number',
        'matchdate' => 'match_date',
        'kickoff' => 'kick_off',
        'team1code' => 'team1_code',
        'team2code' => 'team2_code',
        'venue' => 'venue',
        'stage' => 'stage',

        // Real-world fixtures sheet headers (e.g. FIFA-style exports).
        'matchno' => 'match_number',
        'ko' => 'kick_off',
        // PMA1/PMA2 hold the actual team code (e.g. "QAT-17") - the reliable
        // key, unlike the display-only "TEAM1"/"TEAM2" name columns, which
        // aren't mapped at all.
        'pma1' => 'team1_code',
        'pma2' => 'team2_code',
        'matchround' => 'stage',
    ];

    protected const DATE_FIELDS = ['match_date'];
    protected const TIME_FIELDS = ['kick_off'];

    protected function requiredField(): string
    {
        return 'match_number';
    }

    protected function requiredFieldLabel(): string
    {
        return 'Match Number (or Match No.)';
    }

    protected function mappableFields(): array
    {
        return [
            'match_number',
            'match_date',
            'kick_off',
            'team1_code',
            'team2_code',
            'venue',
            'stage',
        ];
    }

    protected function aiSubject(): string
    {
        return 'a sports-event fixtures list (match schedule)';
    }

    protected function aiGuidance(): string
    {
        return <<<'GUIDANCE'
            - "team1_code" and "team2_code" must be the columns holding each side's
              short team code (e.g. "QAT-17", "BRA"), not the display name columns
              ("TEAM1"/"TEAM2") and not the group-position columns ("A1", "B2").
              A column headed "PMA1"/"PMA2" is usually the code.
            - "stage" is the round or phase, e.g. "Group Stage", "MD-R1",
              "Quarter Final".
            - Ignore result columns entirely: scores, points, win/draw/loss, and
              winner columns have no import field.
            GUIDANCE;
    }
}
