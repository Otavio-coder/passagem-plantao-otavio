<?php

namespace Tests\Unit\Models;

use App\Models\EMR\SbarReport;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Mockery;

class SbarReportTest extends TestCase
{
    /**
     * Test the getBaseQuery method builds the expected SQL query
     */
    public function test_get_base_query_builds_expected_sql()
    {
        // Create a mock connection instance
        $connectionMock = Mockery::mock('Illuminate\Database\Connection');
        
        // Create a query builder mock
        $queryBuilderMock = Mockery::mock('Illuminate\Database\Query\Builder');
        
        // Create a DB raw expression mock
        $rawExpressionMock = Mockery::mock('Illuminate\Database\Query\Expression');
        
        // Set up DB facade mock chain
        DB::shouldReceive('connection')
            ->with('tasy')
            ->andReturn($connectionMock);
            
        DB::shouldReceive('raw')
            ->andReturn($rawExpressionMock);
            
        // Set up connection mock to return query builder
        $connectionMock->shouldReceive('table')
            ->with('tasy.sbar_antimicrobianos_v as a')
            ->andReturn($queryBuilderMock);
            
        // Handle raw queries in leftJoinSub
        $connectionMock->shouldReceive('table')
            ->with(Mockery::type('Illuminate\Database\Query\Expression'))
            ->andReturn($queryBuilderMock);
            
        // Mock query builder methods to return self
        $queryBuilderMock->shouldReceive('join')
            ->andReturn($queryBuilderMock);
            
        $queryBuilderMock->shouldReceive('where')
            ->andReturn($queryBuilderMock);
            
        $queryBuilderMock->shouldReceive('leftJoinSub')
            ->andReturn($queryBuilderMock);
            
        $queryBuilderMock->shouldReceive('orderByRaw')
            ->andReturn($queryBuilderMock);
            
        $queryBuilderMock->shouldReceive('orderByDesc')
            ->andReturn($queryBuilderMock);
            
        $queryBuilderMock->shouldReceive('orderBy')
            ->andReturn($queryBuilderMock);
        
        // Create the model and call getBaseQuery
        $model = new SbarReport();
        $result = $model->getBaseQuery();
        
        // Assert we get the query builder back
        $this->assertSame($queryBuilderMock, $result);
    }
    
    /**
     * Test the getBasicPatientData method includes correct fields
     */
    public function test_get_basic_patient_data_includes_correct_fields()
    {
        // Create a partial mock of the SbarReport model
        $model = $this->createPartialMock(SbarReport::class, ['getBaseQuery']);
        
        // Create a mock query builder
        $queryBuilder = Mockery::mock('Illuminate\Database\Query\Builder');
        
        // Set expectations on the mock
        $model->expects($this->once())
              ->method('getBaseQuery')
              ->willReturn($queryBuilder);
              
        // Expect the select method to be called with specific fields
        $queryBuilder->shouldReceive('select')
                     ->once()
                     ->with(Mockery::on(function($fields) {
                         // Check that essential fields are included
                         $requiredFields = [
                             'a.cd_unidade_basica',
                             'a.cd_pessoa_fisica',
                             'a.nr_atendimento',
                             'sa.cd_setor_atendimento', 
                             'sa.ds_setor_atendimento', 
                             'mew.mews_score',
                             'eval.dt_ultima_aval'
                         ];
                         
                         foreach ($requiredFields as $field) {
                             $found = false;
                             foreach ($fields as $f) {
                                 // Changed comparison to safely handle DB expressions
                                 if (is_string($f) && $f === $field) {
                                     $found = true;
                                     break;
                                 }
                                 
                                 // For DB raw expressions, convert to string safer way
                                 if (is_object($f) && method_exists($f, '__toString')) {
                                     $stringVal = (string)$f;
                                     if (strpos($stringVal, $field) !== false) {
                                         $found = true;
                                         break;
                                     }
                                 }
                                 
                                 // Skip objects that can't be converted to string
                                 if (is_object($f) && !method_exists($f, '__toString')) {
                                     continue; 
                                 }
                             }
                             if (!$found) {
                                 return false;
                             }
                         }
                         return true;
                     }))
                     ->andReturn($queryBuilder);
        
        // Call the method
        $result = $model->getBasicPatientData();
        
        // Assert we get the query builder back
        $this->assertSame($queryBuilder, $result);
    }
    
    /**
     * Test generateFullReport enriches data with additional information
     */
    public function test_generate_full_report_enriches_data()
    {
        // Include all methods we need to mock
        $methodsToMock = [
            'getBasicPatientData', 
            'getBradenScaleData',
            'getMorseScaleData',
            'getMewsDescriptionData',
            'getPewsDescriptionData',
            'getActiveDevicesData',
            'getIsolationStatusData',
            'getAllergiesData',
            'getDiagnosesData',
            'getFallHistoryData',
            'getActivePrecautionsData', 
            'getAntimicrobialMaterialsData',
            'getLastEvaluationDate',
            'getExamPrioritiesData', 
            'getFutureInquiriesData',
            'getScheduledExamsData'
        ];
        
        // Create a partial mock with all necessary methods
        $model = $this->createPartialMock(SbarReport::class, $methodsToMock);
        
        // Set up mock data for getBasicPatientData
        $baseData = collect([
            (object)[
                'cd_setor_atendimento' => '123',
                'ds_setor_atendimento' => 'Test Sector',
                'cd_unidade_basica' => '456',
                'ds_unidade_basica' => 'Test Bed',
                'nr_atendimento' => '789012',
                'cd_pessoa_fisica' => '34567',
                'mews_score' => 3
            ]
        ]);
        
        // Create mock query builder
        $queryBuilder = Mockery::mock('Illuminate\Database\Query\Builder');
        $queryBuilder->shouldReceive('get')->andReturn($baseData);
        
        // Set expectations for mock methods
        $model->expects($this->once())
              ->method('getBasicPatientData')
              ->willReturn($queryBuilder);
              
        $model->expects($this->once())
              ->method('getBradenScaleData')
              ->with('789012')
              ->willReturn([(object)['ds_braden' => 'Test Braden']]);
              
        $model->expects($this->once())
              ->method('getMorseScaleData')
              ->with('789012')
              ->willReturn([(object)['ds_morse' => 'Test Morse']]);
              
        $model->expects($this->once())
              ->method('getMewsDescriptionData')
              ->with('789012')
              ->willReturn([(object)['ds_mews' => 'Test MEWS']]);
              
        $model->expects($this->once())
              ->method('getPewsDescriptionData')
              ->with('789012')
              ->willReturn([(object)['ds_pews' => 'Test PEWS']]);
              
        $model->expects($this->once())
              ->method('getActiveDevicesData')
              ->with('789012')
              ->willReturn([(object)['dispositivos' => 'Test Devices']]);
              
        $model->expects($this->once())
              ->method('getIsolationStatusData')
              ->with('789012')
              ->willReturn([(object)['ds_isolamento' => 'Test Isolation']]);
              
        $model->expects($this->once())
              ->method('getAllergiesData')
              ->with('34567')
              ->willReturn([(object)['alergias_detalhadas' => 'Test Allergies']]);
              
        // Configure the rest of the methods
        $model->expects($this->once())
              ->method('getDiagnosesData')
              ->with('789012')
              ->willReturn([(object)['diag' => 'Test Diagnosis']]);
              
        $model->expects($this->once())
              ->method('getFallHistoryData')
              ->with('789012')
              ->willReturn([(object)['ds_queda' => 'Test Fall']]);
              
        $model->expects($this->once())
              ->method('getActivePrecautionsData')
              ->with('789012')
              ->willReturn([(object)['precaucoes' => 'Test Precautions']]);
              
        $model->expects($this->once())
              ->method('getAntimicrobialMaterialsData')
              ->with('789012')
              ->willReturn([(object)['materiais' => 'Test Materials']]);
              
        $model->expects($this->once())
              ->method('getLastEvaluationDate')
              ->with('789012')
              ->willReturn([(object)['dt_ultima_aval' => 'Test Date']]);
              
        $model->expects($this->once())
              ->method('getExamPrioritiesData')
              ->with('789012')
              ->willReturn([(object)['prioridade_exames' => 'Test Priorities']]);
              
        $model->expects($this->once())
              ->method('getFutureInquiriesData')
              ->with('34567')
              ->willReturn([(object)['futureinquiries' => 'Test Inquiries']]);
              
        $model->expects($this->once())
              ->method('getScheduledExamsData')
              ->with('34567')
              ->willReturn([(object)['exams' => 'Test Exams']]);
        
        // Call the method
        $result = $model->generateFullReport();
        
        // Verify the result
        $this->assertCount(1, $result);
        $this->assertEquals('Test Braden', $result->first()->ds_braden);
        $this->assertEquals('Test Morse', $result->first()->ds_morse);
        $this->assertEquals('Test MEWS', $result->first()->ds_mews);
        $this->assertEquals('Test PEWS', $result->first()->ds_pews);
        $this->assertEquals('Test Devices', $result->first()->dispositivos);
        $this->assertEquals('Test Isolation', $result->first()->ds_isolamento);
        $this->assertEquals('Test Allergies', $result->first()->alergias_detalhadas);
        $this->assertEquals('Test Diagnosis', $result->first()->diag);
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
