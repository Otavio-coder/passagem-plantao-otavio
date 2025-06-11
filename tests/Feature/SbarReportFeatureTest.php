<?php

namespace Tests\Feature;

use App\Models\EMR\SbarReport;
use App\Livewire\SbarReport as SbarReportComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tests\TestCase;
use Mockery;

class SbarReportFeatureTest extends TestCase
{
    /**
     * Test that the SbarReport model can be used to generate a full report
     * just like it's used in the Livewire component
     */
    public function test_generate_full_report()
    {
        $mockModel = $this->createPartialMock(SbarReport::class, ['getBasicPatientData']);
        
        // Create a sample result collection that mimics what would come from the database
        $mockResult = collect([
            (object)[
                'cd_setor_atendimento' => '123',
                'ds_setor_atendimento' => 'Test Sector',
                'cd_unidade_basica' => '456',
                'ds_unidade_basica' => 'Test Bed',
                'nr_atendimento' => '789012',
                'cd_pessoa_fisica' => '34567',
                'mews_score' => 3,
                'dt_ultima_aval' => now()->subHours(2),
                'convenio' => 'Test Health Plan'
            ]
        ]);
        
        // Mock the database query to return our test data
        $queryBuilderMock = Mockery::mock('Illuminate\Database\Query\Builder');
        $queryBuilderMock->shouldReceive('get')->andReturn($mockResult);
        
        // Set up the model to return our mock query builder
        $mockModel->method('getBasicPatientData')
                 ->willReturn($queryBuilderMock);
        
        // Create mock methods for all the auxiliary data methods
        $this->mockAuxiliaryMethods($mockModel);
        
        // Test the generateFullReport method
        $result = $mockModel->generateFullReport();
        
        // Assert that we got the expected result
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertNotEmpty($result);
        $this->assertEquals('123', $result->first()->cd_setor_atendimento);
        $this->assertEquals('Test Sector', $result->first()->ds_setor_atendimento);
        $this->assertEquals('456', $result->first()->cd_unidade_basica);
        $this->assertEquals('Test Bed', $result->first()->ds_unidade_basica);
        $this->assertEquals(3, $result->first()->mews_score);
    }
    
    /**
     * Test the Livewire component with the SbarReport model
     */
    public function test_livewire_component()
    {
        // Create a mock of the SbarReport model
        $mockModel = Mockery::mock(SbarReport::class);
        
        // Set up mock data that resembles what would be returned from the model
        $mockData = collect([
            (object)[
                'cd_setor_atendimento' => '123',
                'ds_setor_atendimento' => 'Test Sector',
                'cd_unidade_basica' => '456',
                'ds_unidade_basica' => 'Test Bed',
                'nr_atendimento' => '789012',
                'cd_pessoa_fisica' => '34567',
                'mews_score' => 3,
                'dt_ultima_aval' => now()->subHours(2),
                'convenio' => 'Test Health Plan',
                'dispositivos' => 'Test Devices',
                'ds_isolamento' => '( X )Sim  (   )Não',
                'diag' => 'Test Diagnosis'
            ],
            (object)[
                'cd_setor_atendimento' => '123',
                'ds_setor_atendimento' => 'Test Sector',
                'cd_unidade_basica' => '789',
                'ds_unidade_basica' => 'Another Bed',
                'nr_atendimento' => '789013',
                'cd_pessoa_fisica' => '34568',
                'mews_score' => 5,
                'dt_ultima_aval' => now()->subHours(1),
                'convenio' => 'Another Health Plan',
                'dispositivos' => null,
                'ds_isolamento' => '(   )Sim  ( X )Não',
                'diag' => null
            ]
        ]);
        
        // Set expectations on the mock
        $mockModel->shouldReceive('generateFullReport')
                 ->andReturn($mockData);
                 
        // Register mock with the container
        app()->instance(SbarReport::class, $mockModel);
        
        // Test the Livewire component
        Livewire::test(SbarReportComponent::class)
            ->assertSet('loading', true)
            ->assertSet('errorMessage', null)
            ->assertViewHas('sectors')
            ->assertViewHas('totalPatients');
            
        // Test sector filtering
        Livewire::test(SbarReportComponent::class)
            ->set('sectorFilter', '123')
            ->call('render')
            ->assertViewHas('sectors')
            ->assertViewHas('totalPatients', 2);
            
        // Test bed filtering
        Livewire::test(SbarReportComponent::class)
            ->set('sectorFilter', '123')
            ->set('bedFilter', '789')
            ->call('render')
            ->assertViewHas('sectors')
            ->assertViewHas('totalPatients', 1);
    }
    
    /**
     * Test error handling in the SbarReport model when used with Livewire
     */
    public function test_error_handling()
    {
        // Create a mock of the SbarReport model that throws an exception
        $mockModel = Mockery::mock(SbarReport::class);
        $mockModel->shouldReceive('generateFullReport')
                 ->andThrow(new \Exception('Test error message'));
                 
        // Register mock with the container
        app()->instance(SbarReport::class, $mockModel);
        
        // Test the Livewire component handles the error
        Livewire::test(SbarReportComponent::class)
            ->call('render')
            ->assertSet('errorMessage', 'Erro ao carregar dados: Test error message')
            ->assertViewHas('sectors')
            ->assertViewHas('totalPatients', 0);
    }
    
    /**
     * Test the getSectorsWithBeds method of the Livewire component
     * which is essential for displaying data correctly
     */
    public function test_get_sectors_with_beds()
    {
        // Create a sample collection of report data
        $reportData = collect([
            (object)[
                'cd_setor_atendimento' => '123',
                'ds_setor_atendimento' => 'Test Sector',
                'cd_unidade_basica' => '456',
                'ds_unidade_basica' => 'Test Bed',
                'nr_atendimento' => '789012'
            ],
            (object)[
                'cd_setor_atendimento' => '123',
                'ds_setor_atendimento' => 'Test Sector',
                'cd_unidade_basica' => '789',
                'ds_unidade_basica' => 'Another Bed',
                'nr_atendimento' => '789013'
            ],
            (object)[
                'cd_setor_atendimento' => '456',
                'ds_setor_atendimento' => 'Another Sector',
                'cd_unidade_basica' => '101',
                'ds_unidade_basica' => 'Third Bed',
                'nr_atendimento' => '789014'
            ]
        ]);
        
        // Instantiate the Livewire component
        $component = new SbarReportComponent();
        
        // Use reflection to access the private method
        $reflection = new \ReflectionClass($component);
        $method = $reflection->getMethod('getSectorsWithBeds');
        $method->setAccessible(true);
        
        // Call the method with our test data
        $result = $method->invokeArgs($component, [$reportData]);
        
        // Verify the structure of the result
        $this->assertCount(2, $result); // Should have 2 sectors
        
        // Check first sector
        $sector123 = $result['sector_123'];
        $this->assertEquals('123', $sector123['id']);
        $this->assertEquals('Test Sector', $sector123['name']);
        $this->assertCount(2, $sector123['beds']); // Should have 2 beds
        $this->assertEquals(2, $sector123['patientCount']);
        
        // Check second sector
        $sector456 = $result['sector_456'];
        $this->assertEquals('456', $sector456['id']);
        $this->assertEquals('Another Sector', $sector456['name']);
        $this->assertCount(1, $sector456['beds']); // Should have 1 bed
        $this->assertEquals(1, $sector456['patientCount']);
        
        // Check beds in first sector
        $this->assertTrue(isset($sector123['beds']['bed_456']));
        $this->assertTrue(isset($sector123['beds']['bed_789']));
        
        // Check patient in bed
        $this->assertCount(1, $sector123['beds']['bed_456']['patients']);
        $this->assertEquals('789012', $sector123['beds']['bed_456']['patients'][0]->nr_atendimento);
    }

    /**
     * Helper method to mock all auxiliary methods used in generateFullReport
     */
    private function mockAuxiliaryMethods($mockModel)
    {
        // Create a standard mock result for all auxiliary methods
        $standardResult = [(object)['result' => 'mock data']];
        
        // List of methods to mock
        $methodsToMock = [
            'getBradenScaleData',
            'getMorseScaleData',
            'getMewsDescriptionData',
            'getPewsDescriptionData',
            'getActiveDevicesData',
            'getIsolationStatusData',
            'getDiagnosesData',
            'getFallHistoryData',
            'getAllergiesData',
            'getActivePrecautionsData',
            'getAntimicrobialMaterialsData',
            'getLastEvaluationDate',
            'getExamPrioritiesData',
            'getFutureInquiriesData',
            'getScheduledExamsData'
        ];
        
        // Mock each method
        foreach ($methodsToMock as $method) {
            $mockModel->method($method)->willReturn($standardResult);
        }
    }
    
    /**
     * Clean up after tests
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
