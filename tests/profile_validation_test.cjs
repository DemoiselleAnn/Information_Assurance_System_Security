const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const window = {};
vm.runInNewContext(fs.readFileSync(__dirname + '/../js/my_profile.js', 'utf8'), {
    window, document: {readyState: 'loading', addEventListener() {}}
});
const cases = JSON.parse(execFileSync('C:/xampp/php/php.exe', [__dirname + '/profile_validation_test.php', '--json'], {encoding:'utf8'}));
for (const {field, value, expected} of cases) {
    assert.equal(!window.profileFieldError(field, value), expected, `${field}: ${JSON.stringify(value)}`);
}
assert.equal(window.profileFieldError('barangay', 'Buhang', 'Buhang'), '');
assert.notEqual(window.profileFieldError('barangay', 'Other Legacy', 'Buhang'), '');
console.log(`PASS: ${cases.length} JavaScript/PHP validation parity cases and legacy compatibility.`);
