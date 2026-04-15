<?php

namespace Tests\Unit;

use App\Repositories\EMR\PatientClinicalRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PatientClinicalRepositoryTest extends TestCase
{
    #[Test]
    #[DataProvider('cidStripProvider')]
    public function it_strips_cid_code_prefix_from_description(string $code, string $desc, string $expected): void
    {
        $result = PatientClinicalRepository::stripCidCodeFromDescription($code, $desc);

        $this->assertSame($expected, $result);
    }

    public static function cidStripProvider(): array
    {
        return [
            'code without dot followed by code with dot' => [
                'D72.9',
                'D729 D72.9 Transt NE dos globulos brancos',
                'Transt NE dos globulos brancos',
            ],
            'only code with dot prefix' => [
                'D72.9',
                'D72.9 Transtorno não especificado dos glóbulos brancos',
                'Transtorno não especificado dos glóbulos brancos',
            ],
            'only code without dot prefix' => [
                'D72.9',
                'D729 Transtorno não especificado',
                'Transtorno não especificado',
            ],
            'description already clean (from CID_DOENCA table)' => [
                'D72.9',
                'Transtorno não especificado dos glóbulos brancos',
                'Transtorno não especificado dos glóbulos brancos',
            ],
            'code without dot stored in cd_cid' => [
                'D729',
                'D729 Transt NE dos globulos brancos',
                'Transt NE dos globulos brancos',
            ],
            'undotted code with dotted prefix in description' => [
                'R520',
                'R52.0 Dor aguda',
                'Dor aguda',
            ],
            'empty code returns desc unchanged' => [
                '',
                'D729 D72.9 Transt NE',
                'D729 D72.9 Transt NE',
            ],
            'empty desc returns empty string' => [
                'D72.9',
                '',
                '',
            ],
            'different code, no strip' => [
                'J18.9',
                'D729 D72.9 Transt NE',
                'D729 D72.9 Transt NE',
            ],
        ];
    }
}
