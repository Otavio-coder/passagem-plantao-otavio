import { Selector } from 'testcafe';

fixture `SBAR Model Tests`
    .page `http://localhost/test/sbar-model`;

test('Test SBAR model methods return data', async t => {
    // Test Braden Scale Data
    await t
        .click(Selector('#method'))
        .click(Selector('option').withText('getBradenScaleData'))
        .typeText(Selector('#attendance'), '20963941', { replace: true })
        .click(Selector('#testButton'))
        .wait(5000); // Wait for result
    
    // Check if result contains expected data structure
    const resultText = await Selector('#resultArea').innerText;
    const resultObj = JSON.parse(resultText);
    
    await t.expect(resultObj.success).eql(true);
    await t.expect(resultObj.data).ok('Braden scale data should be returned');
    
    // Test Morse Scale Data
    await t
        .click(Selector('#method'))
        .click(Selector('option').withText('getMorseScaleData'))
        .click(Selector('#testButton'))
        .wait(5000); // Wait for result
    
    // Check if result contains expected data
    const morseResult = await Selector('#resultArea').innerText;
    const morseObj = JSON.parse(morseResult);
    
    await t.expect(morseObj.success).eql(true);
    await t.expect(morseObj.data).ok('Morse scale data should be returned');
    
    // Test full report generation
    await t
        .click(Selector('#method'))
        .click(Selector('option').withText('generateFullReport'))
        .click(Selector('#testButton'))
        .wait(10000); // Wait longer for full report
    
    // Check if full report is generated
    const reportResult = await Selector('#resultArea').innerText;
    const reportObj = JSON.parse(reportResult);
    
    await t.expect(reportObj.success).eql(true);
    await t.expect(reportObj.data).ok('Full report should be returned');
});
