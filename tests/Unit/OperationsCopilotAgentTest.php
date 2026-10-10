<?php

namespace Tests\Unit;

use App\Ai\Agents\OperationsCopilotAgent;
use Tests\TestCase;

class OperationsCopilotAgentTest extends TestCase
{
    private function roles(OperationsCopilotAgent $agent): array
    {
        return array_map(fn ($m) => $m->role->value.':'.$m->content, [...$agent->messages()]);
    }

    public function test_earlier_turns_become_the_conversation(): void
    {
        $agent = new OperationsCopilotAgent([
            ['role' => 'user', 'content' => 'movements on 27 Nov'],
            ['role' => 'assistant', 'content' => 'Two movements: Ireland, Mexico'],
        ]);

        $this->assertSame(['user:movements on 27 Nov', 'assistant:Two movements: Ireland, Mexico'], $this->roles($agent));
    }

    public function test_turns_that_break_the_alternation_are_dropped(): void
    {
        $agent = new OperationsCopilotAgent([
            ['role' => 'assistant', 'content' => 'stray reply'],
            ['role' => 'user', 'content' => 'first'],
            ['role' => 'user', 'content' => 'doubled'],
            ['role' => 'assistant', 'content' => 'answer'],
            ['role' => 'bogus', 'content' => 'ignored'],
            ['role' => 'user', 'content' => 'unanswered'],
        ]);

        $this->assertSame(['user:first', 'assistant:answer'], $this->roles($agent));
    }

    public function test_no_history_means_no_messages(): void
    {
        $this->assertSame([], $this->roles(new OperationsCopilotAgent));
    }
}
