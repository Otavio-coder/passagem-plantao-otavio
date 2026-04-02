<?php

namespace Tests\Unit;

use App\Support\PendingEventTypeClassifier;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PendingEventTypeClassifierTest extends TestCase
{
    #[Test]
    public function classifies_pending_exam_by_tipo(): void
    {
        $type = PendingEventTypeClassifier::fromPendingEvent([
            'tipo' => 'exame',
            'descricao' => 'Troponina',
        ]);

        $this->assertSame(PendingEventTypeClassifier::EXAM, $type);
    }

    #[Test]
    public function classifies_pending_procedure_by_tipo(): void
    {
        $type = PendingEventTypeClassifier::fromPendingEvent([
            'tipo' => 'procedimento',
            'descricao' => 'Cateterismo',
        ]);

        $this->assertSame(PendingEventTypeClassifier::PROCEDURE, $type);
    }

    #[Test]
    public function keeps_hemotherapy_as_own_pending_type(): void
    {
        $type = PendingEventTypeClassifier::fromPendingEvent([
            'tipo' => 'hemoterapia',
            'descricao' => 'Concentrado de hemacias',
        ]);

        $this->assertSame(PendingEventTypeClassifier::HEMOTHERAPY, $type);
        $this->assertSame('Hemoterapia', PendingEventTypeClassifier::label($type));
    }

    #[Test]
    public function keeps_chemotherapy_as_own_pending_type(): void
    {
        $type = PendingEventTypeClassifier::fromPendingEvent([
            'tipo' => 'quimioterapia',
            'descricao' => 'Protocolo X',
        ]);

        $this->assertSame(PendingEventTypeClassifier::CHEMOTHERAPY, $type);
        $this->assertSame('Quimioterapia', PendingEventTypeClassifier::label($type));
    }

    #[Test]
    public function classifies_therapeutic_procedure_by_event_type(): void
    {
        $type = PendingEventTypeClassifier::fromTherapeuticProcedure([
            'event_type' => 'procedimento',
            'name' => 'ECG',
        ]);

        $this->assertSame(PendingEventTypeClassifier::PROCEDURE, $type);
    }

    #[Test]
    public function classifies_therapeutic_exam_by_event_type(): void
    {
        $type = PendingEventTypeClassifier::fromTherapeuticProcedure([
            'event_type' => 'exame',
            'name' => 'Hemograma',
        ]);

        $this->assertSame(PendingEventTypeClassifier::EXAM, $type);
    }
}
