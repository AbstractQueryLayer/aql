<?php

declare(strict_types=1);

namespace IfCastle\AQL\AqlService;

use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\AQL\Executor\AqlExecutorInterface;
use IfCastle\AQL\Result\InsertUpdateResultInterface;
use IfCastle\AQL\Result\ResultInterface;
use IfCastle\AQL\Result\TupleInterface;
use IfCastle\RestApi\Rest;
use IfCastle\ServiceManager\AsPublicService;

#[AsPublicService]
final readonly class AqlService
{
    public function __construct(
        private AqlExecutorInterface $aqlExecutor,
    ) {}


    #[Rest(path: '/aql', methods: [Rest::GET, Rest::POST, Rest::PUT, Rest::DELETE])]
    public function aqlQuery(string $aqlQuery): QueryResult
    {
        $parsedAqlQuery             = $this->parseAqlQuery($aqlQuery);

        $this->validateAqlQuery($parsedAqlQuery);

        return $this->formatResult($this->aqlExecutor->executeAql($parsedAqlQuery));
    }

    private function parseAqlQuery(string $aqlQuery): QueryInterface {}

    private function validateAqlQuery(mixed $parsedAqlQuery): void
    {
        // Validate the AQL query
    }

    private function formatResult(ResultInterface|TupleInterface|InsertUpdateResultInterface $result): QueryResult
    {
        // Format the result
    }
}
