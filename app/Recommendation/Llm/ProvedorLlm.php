<?php

namespace App\Recommendation\Llm;

interface ProvedorLlm
{
    public function modelo(): string;

    public function preparar(int $timeout): void;

    public function gerarJson(array $prompts, int $timeout, ?array $schema = null): array;
}
