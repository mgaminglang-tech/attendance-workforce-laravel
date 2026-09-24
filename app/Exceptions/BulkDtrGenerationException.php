<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Response;
use Throwable;

class BulkDtrGenerationException extends Exception
{
    public function __construct(
        private int $departmentId,
        private string $month,
        ?Throwable $previous = null,
    ) {
        parent::__construct('Unable to generate the department DTR archive.', previous: $previous);
    }

    /** @return array{department_id: int, month: string} */
    public function context(): array
    {
        return [
            'department_id' => $this->departmentId,
            'month' => $this->month,
        ];
    }

    public function render(): Response
    {
        return response(
            'The department DTR archive could not be generated. Please try again.',
            Response::HTTP_INTERNAL_SERVER_ERROR,
        );
    }
}
