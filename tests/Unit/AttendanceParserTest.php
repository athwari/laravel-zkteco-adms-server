<?php

use Athwari\ZktecoAdms\Services\AttendanceParser;

beforeEach(function () {
    $this->parser = new AttendanceParser();
});

// ---------------------------------------------------------------
// Serial Number Validation
// ---------------------------------------------------------------

test('valid serial numbers', function () {
    expect($this->parser->validateSerialNumber('ABC123'))->toBeTrue()
        ->and($this->parser->validateSerialNumber('DEVICE-001'))->toBeTrue()
        ->and($this->parser->validateSerialNumber('device_test_123'))->toBeTrue()
        ->and($this->parser->validateSerialNumber('A'))->toBeTrue();
});

test('invalid serial numbers', function () {
    expect($this->parser->validateSerialNumber(''))->toBeFalse()
        ->and($this->parser->validateSerialNumber('has space'))->toBeFalse()
        ->and($this->parser->validateSerialNumber('has.dot'))->toBeFalse()
        ->and($this->parser->validateSerialNumber(str_repeat('A', 65)))->toBeFalse();
});

// ---------------------------------------------------------------
// ATTLOG Parsing
// ---------------------------------------------------------------

test('parse single attendance record', function () {
    $data = "1001\t2024-03-15 08:30:00\t0\t1\t";
    $records = $this->parser->parseAttendanceRecords($data, 'TEST001');

    expect($records)->toHaveCount(1)
        ->and($records[0]->userId)->toBe('1001')
        ->and($records[0]->timestamp->format('Y-m-d H:i:s'))->toBe('2024-03-15 08:30:00')
        ->and($records[0]->status)->toBe(0)
        ->and($records[0]->verifyMode)->toBe(1)
        ->and($records[0]->serialNumber)->toBe('TEST001');
});

test('parse multiple attendance records', function () {
    $data = "1001\t2024-03-15 08:30:00\t0\t1\t\n1002\t2024-03-15 08:31:00\t1\t4\tWC01";
    $records = $this->parser->parseAttendanceRecords($data, 'TEST001');

    expect($records)->toHaveCount(2)
        ->and($records[1]->userId)->toBe('1002')
        ->and($records[1]->status)->toBe(1)
        ->and($records[1]->verifyMode)->toBe(4)
        ->and($records[1]->workCode)->toBe('WC01');
});

test('parse unix epoch timestamp', function () {
    $epoch = '1710488400'; // 2024-03-15 08:00:00 UTC
    $data = "1001\t{$epoch}\t0\t1\t";
    $records = $this->parser->parseAttendanceRecords($data, 'TEST001');

    expect($records)->toHaveCount(1)
        ->and($records[0]->timestamp->getTimestamp())->toBe(1710488400);
});

test('skip malformed lines', function () {
    $data = "too_few_fields\n1001\t2024-03-15 08:30:00\t0\t1\t";
    $records = $this->parser->parseAttendanceRecords($data, 'TEST001');

    expect($records)->toHaveCount(1)
        ->and($records[0]->userId)->toBe('1001');
});

test('skip empty user id', function () {
    $data = "\t2024-03-15 08:30:00\t0\t1\t";
    $records = $this->parser->parseAttendanceRecords($data, 'TEST001');

    expect($records)->toHaveCount(0);
});

test('skip unparseable timestamp', function () {
    $data = "1001\tnot-a-date\t0\t1\t";
    $records = $this->parser->parseAttendanceRecords($data, 'TEST001');

    expect($records)->toHaveCount(0);
});

test('handles crlf line endings', function () {
    $data = "1001\t2024-03-15 08:30:00\t0\t1\t\r\n1002\t2024-03-15 08:31:00\t1\t1\t";
    $records = $this->parser->parseAttendanceRecords($data, 'TEST001');

    expect($records)->toHaveCount(2);
});

test('minimal fields', function () {
    $data = "1001\t2024-03-15 08:30:00";
    $records = $this->parser->parseAttendanceRecords($data, 'TEST001');

    expect($records)->toHaveCount(1)
        ->and($records[0]->status)->toBe(0)
        ->and($records[0]->verifyMode)->toBe(0)
        ->and($records[0]->workCode)->toBe('');
});

// ---------------------------------------------------------------
// KV Pair Parsing
// ---------------------------------------------------------------

test('parse kv pairs newline separated', function () {
    $data = "FWVersion=Ver 8.1.1\nDeviceName=TestDevice\nIPAddress=192.168.1.100";
    $result = $this->parser->parseKVPairs($data, "\n");

    expect($result['FWVersion'])->toBe('Ver 8.1.1')
        ->and($result['DeviceName'])->toBe('TestDevice')
        ->and($result['IPAddress'])->toBe('192.168.1.100');
});

test('parse kv pairs with tilde transform', function () {
    $data = '~DeviceName=Test,~FWVersion=1.0,~MACAddress=AA:BB:CC';
    $result = $this->parser->parseKVPairs($data, ',', [AttendanceParser::class, 'trimTildePrefix']);

    expect($result['DeviceName'])->toBe('Test')
        ->and($result['FWVersion'])->toBe('1.0');
});

// ---------------------------------------------------------------
// User Record Parsing
// ---------------------------------------------------------------

test('parse user records', function () {
    $data = "PIN=1\tName=John Doe\tPrivilege=0\tCard=12345\tPassword=pass";
    $records = $this->parser->parseUserRecords($data, 'TEST001');

    expect($records)->toHaveCount(1)
        ->and($records[0]->pin)->toBe('1')
        ->and($records[0]->name)->toBe('John Doe')
        ->and($records[0]->privilege)->toBe(0)
        ->and($records[0]->card)->toBe('12345')
        ->and($records[0]->password)->toBe('pass');
});

test('skip user record without pin', function () {
    $data = "Name=John Doe\tPrivilege=0";
    $records = $this->parser->parseUserRecords($data, 'TEST001');

    expect($records)->toHaveCount(0);
});

// ---------------------------------------------------------------
// Command Result Parsing
// ---------------------------------------------------------------

test('parse single command result', function () {
    $body = 'ID=1&Return=0&CMD=INFO';
    $results = $this->parser->parseCommandResults($body, 'TEST001');

    expect($results)->toHaveCount(1)
        ->and($results[0]->id)->toBe(1)
        ->and($results[0]->returnCode)->toBe(0)
        ->and($results[0]->command)->toBe('INFO')
        ->and($results[0]->isSuccess())->toBeTrue();
});

test('parse batched command results', function () {
    $body = "ID=1&Return=0&CMD=DATA\nID=2&Return=0&CMD=DATA";
    $results = $this->parser->parseCommandResults($body, 'TEST001');

    expect($results)->toHaveCount(2)
        ->and($results[0]->id)->toBe(1)
        ->and($results[1]->id)->toBe(2);
});

test('parse failed command result', function () {
    $body = 'ID=5&Return=-1&CMD=CHECK';
    $results = $this->parser->parseCommandResults($body, 'TEST001');

    expect($results)->toHaveCount(1)
        ->and($results[0]->returnCode)->toBe(-1)
        ->and($results[0]->isSuccess())->toBeFalse();
});

test('parse shell multiline format', function () {
    $body = "ID=32\nReturn=0\nCMD=Shell\nContent=some output";
    $results = $this->parser->parseCommandResults($body, 'TEST001');

    expect($results)->toHaveCount(1)
        ->and($results[0]->id)->toBe(32)
        ->and($results[0]->command)->toBe('Shell');
});
