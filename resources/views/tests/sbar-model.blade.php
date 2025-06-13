<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test SBAR Model</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>
    <div class="container my-4">
        <h1>SBAR Model Function Testing</h1>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Test Model Methods</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="method" class="form-label">Method to Test</label>
                    <select class="form-select" id="method">
                        <option value="getPatientScales">getPatientScales (PatientScales)</option>
                        <option value="getMewsDataForPatients">getMewsDataForPatients (PatientScales)</option>
                        <option value="getPainScaleDescription">getPainScaleDescription (PatientScales)</option>
                        <option value="getTevDescription">getTevDescription (PatientScales)</option>
                        <option value="getPatientClinicalDetails">getPatientClinicalDetails (PatientClinical)</option>
                        <option value="getClinicalAlertsForPatients">getClinicalAlertsForPatients (PatientClinical)</option>
                        <option value="getPatientActiveAlerts">getPatientActiveAlerts (PatientClinical)</option>
                        <option value="getPatientBasicDetails">getPatientBasicDetails (Patient)</option>
                        <option value="getBasicPatientData">getBasicPatientData (Patient)</option>
                        <option value="getPatientDetails">getPatientDetails (SbarReport)</option>
                        <option value="getBasePatientData">getBasePatientData (SbarReport)</option>
                    </select>
                </div>
                
                <div class="mb-3" id="attendanceField">
                    <label for="attendance" class="form-label">Attendance Number</label>
                    <input type="text" class="form-control" id="attendance" value="20963941">
                </div>
                
                <div class="mb-3" id="personField" style="display:none;">
                    <label for="personId" class="form-label">Person ID</label>
                    <input type="text" class="form-control" id="personId" value="">
                </div>
                
                <div class="mb-3" id="sectorField" style="display:none;">
                    <label for="sectorFilter" class="form-label">Sector Filter</label>
                    <input type="text" class="form-control" id="sectorFilter" value="%HSR-U.I%">
                </div>
                
                <button class="btn btn-primary" id="testButton">Test Method</button>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5>Result</h5>
                <div class="spinner-border text-primary d-none" id="loadingSpinner" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
            <div class="card-body">
                <pre id="resultArea" class="bg-light p-3" style="max-height: 500px; overflow-y: auto;">Results will appear here...</pre>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const methodSelect = document.getElementById('method');
            const attendanceField = document.getElementById('attendanceField');
            const personField = document.getElementById('personField');
            const sectorField = document.getElementById('sectorField');
            const testButton = document.getElementById('testButton');
            const resultArea = document.getElementById('resultArea');
            const loadingSpinner = document.getElementById('loadingSpinner');
            
            // Update visible fields based on selected method
            methodSelect.addEventListener('change', function() {
                const method = this.value;
                
                // Reset all fields
                attendanceField.style.display = 'none';
                personField.style.display = 'none';
                sectorField.style.display = 'none';
                
                // Show relevant fields
                if (['getAllergiesData', 'getFutureInquiriesData', 'getScheduledExamsData'].includes(method)) {
                    personField.style.display = 'block';
                } else if (['getBasicPatientData', 'generateFullReport'].includes(method)) {
                    sectorField.style.display = 'block';
                } else {
                    attendanceField.style.display = 'block';
                }
            });
            
            // Initialize with default selection
            methodSelect.dispatchEvent(new Event('change'));
            
            // Test button click handler
            testButton.addEventListener('click', async function() {
                const method = methodSelect.value;
                const attendance = document.getElementById('attendance').value;
                const personId = document.getElementById('personId').value;
                const sectorFilter = document.getElementById('sectorFilter').value;
                
                loadingSpinner.classList.remove('d-none');
                resultArea.textContent = 'Loading...';
                
                try {
                    // Prepare request data
                    const data = { method };
                    if (['getAllergiesData', 'getFutureInquiriesData', 'getScheduledExamsData'].includes(method)) {
                        data.person_id = personId;
                    } else if (['getBasicPatientData', 'generateFullReport'].includes(method)) {
                        data.sector_filter = sectorFilter;
                    } else {
                        data.attendance = attendance;
                    }
                    
                    // Send request
                    const response = await fetch('{{ route("test.sbar-model.debug") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify(data)
                    });
                    
                    const result = await response.json();
                    resultArea.textContent = JSON.stringify(result, null, 2);
                    
                } catch (error) {
                    resultArea.textContent = 'Error: ' + error.message;
                } finally {
                    loadingSpinner.classList.add('d-none');
                }
            });
        });
    </script>
</body>
</html>
