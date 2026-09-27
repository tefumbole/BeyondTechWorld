<?php

namespace App\Console\Commands;

use App\Services\Assistant\AssistantAiConfig;
use App\Services\Assistant\AssistantDirectConversationTester;
use Illuminate\Console\Command;

class AssistantDirectConversationTest extends Command
{
    protected $signature = 'assistant:direct-test
                            {message? : Optional single message to test}
                            {--battery : Run the five direct-answer battery questions}
                            {--with-tools : Offer ERP tools (tool_choice still auto)}
                            {--json : Print JSON}';

    protected $description = 'Admin diagnostic: send a message through OpenAI with the production Mbole AI system prompt (bypasses WhatsApp/website routing).';

    public function handle(AssistantDirectConversationTester $tester)
    {
        $this->line('OpenAI key configured: '.(AssistantAiConfig::isConfigured() ? 'YES' : 'NO'));
        $this->line('Model: '.AssistantAiConfig::model());
        $this->line('Provider setting: '.AssistantAiConfig::providerName());

        if ($this->option('battery')) {
            $rows = $tester->battery();
            if ($this->option('json')) {
                $this->line(json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                return 0;
            }
            foreach ($rows as $row) {
                $this->line(str_repeat('-', 60));
                $this->info($row['input']);
                $this->line('source='.$row['response_source'].' model='.$row['model'].' tools='.($row['tool_requested'] ?: 'none'));
                $this->line(mb_substr((string) $row['final_text'], 0, 400));
                if (! empty($row['error'])) {
                    $this->error('error: '.$row['error']);
                }
            }
            $direct = count(array_filter($rows, function ($r) {
                return ($r['response_source'] ?? '') === 'OPENAI_DIRECT';
            }));
            $this->line(str_repeat('=', 60));
            $this->info("OPENAI_DIRECT: {$direct}/".count($rows));

            return $direct === count($rows) ? 0 : 1;
        }

        $message = $this->argument('message');
        if ($message === null || $message === '') {
            $message = 'What is the difference between a line array and a point source speaker?';
        }
        $result = $tester->run($message, ['with_tools' => (bool) $this->option('with-tools')]);
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return ! empty($result['http_success']) && ($result['response_source'] ?? '') === 'OPENAI_DIRECT' ? 0 : 1;
        }
        $this->line('http_success: '.(! empty($result['http_success']) ? 'YES' : 'NO'));
        $this->line('response_source: '.($result['response_source'] ?? ''));
        $this->line('tool_choice: '.($result['tool_choice'] ?? ''));
        $this->line('tool_requested: '.($result['tool_requested'] ?: 'none'));
        $this->line('latency_ms: '.($result['latency_ms'] ?? ''));
        if (! empty($result['error'])) {
            $this->error('error: '.$result['error']);
        }
        $this->line('final_text:');
        $this->line((string) ($result['final_text'] ?? ''));

        return (! empty($result['http_success']) && ($result['response_source'] ?? '') === 'OPENAI_DIRECT') ? 0 : 1;
    }
}
