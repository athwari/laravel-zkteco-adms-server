<?php

namespace Athwari\ZktecoAdms\Tests\Unit;

use Athwari\ZktecoAdms\Services\AttendanceParser;
use Athwari\ZktecoAdms\Tests\TestCase;

class AttendanceParserTest extends TestCase
{
    private AttendanceParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new AttendanceParser();
    }

    // ---------------------------------------------------------------
    // Serial Number Validation
    // ---------------------------------------------------------------

    public function test_valid_serial_numbers(): void
    {
        $this->assertTrue($this->parser->validateSerialNumber('ABC123'));
        $this->assertTrue($this->parser->validateSerialNumber('DEVICE-001'));
        $this->assertTrue($this->parser->validateSerialNumber('device_test_123'));
        $this->assertTrue($this->parser->validateSerialNumber('A'));
    }

    public function test_invalid_serial_numbers(): void
    {
        $this->assertFalse($this->parser->validateSerialNumber(''));
        $this->assertFalse($this->parser->validateSerialNumber('has space'));
        $this->assertFalse($this->parser->validateSerialNumber('has.dot'));
        $this->assertFalse($this->parser->validateSerialNumber(str_repeat('A', 65)));
    }

    // ---------------------------------------------------------------
    // ATTLOG Parsing
    // ---------------------------------------------------------------

    public function test_parse_single_attendance_record(): void
    {
        $data = "1001\t2024-03-15 08:30:00\t0\t1\t";
        $records = $this->parser->parseAttendanceRecords($data, 'TEST001');

        $this->assertCount(1, $records);
        $this->assertEquals('1001', $records[0]->userId);
        $this->assertEquals('2024-03-15 08:30:00', $records[0]->timestamp->format('Y-m-d H:i:s'));
        $this->assertEquals(0, $records[0]->status);
        $this->assertEquals(1, $records[0]->verifyMode);
        $this->assertEquals('TEST001', $records[0]->serialNumber);
    }

    public function test_parse_multiple_attendance_records(): void
    {
        $data = "1001\t2024-03-15 08:30:00\t0\t1\t\n1002\t2024-03-15 08:31:00\t1\t4\tWC01";
        $records = $this->parser->parseAttendanceRecords($data, 'TEST001');

        $this->assertCount(2, $records);
        $this->assertEquals('1002', $records[1]->userId);
        $this->assertEquals(1, $records[1]->status);
        $this->assertEquals(4, $records[1]->verifyMode);
        $this->assertEquals('WC01', $records[1]->workCode);
    }

    public function test_parse_unix_epoch_timestamp(): void
    {
        $epoch = '1710488400'; // 2024-03-15 08:00:00 UTC
        $data = "1001\t{$epoch}\t0\t1\t";
        $records = $this->parser->parseAttendanceRecords($data, 'TEST001');

        $this->assertCount(1, $records);
        $this->assertEquals(1710488400, $records[0]->timestamp->getTimestamp());
    }

    public function test_skip_malformed_lines(): void
    {
        $data = "too_few_fields\n1001\t2024-03-15 08:30:00\t0\t1\t";
        $records = $this->parser->parseAttendanceRecords($data, 'TEST001');

        $this->assertCount(1, $records);
        $this->assertEquals('1001', $records[0]->userId);
    }

    public function test_skip_empty_user_id(): void
    {
        $data = "\t2024-03-15 08:30:00\t0\t1\t";
        $records = $this->parser->parseAttendanceRecords($data, 'TEST001');

        $this->assertCount(0, $records);
    }

    public function test_skip_unparseable_timestamp(): void
    {
        $data = "1001\tnot-a-date\t0\t1\t";
        $records = $this->parser->parseAttendanceRecords($data, 'TEST001');

        $this->assertCount(0, $records);
    }

    public function test_handles_crlf_line_endings(): void
    {
        $data = "1001\t2024-03-15 08:30:00\t0\t1\t\r\n1002\t2024-03-15 08:31:00\t1\t1\t";
        $records = $this->parser->parseAttendanceRecords($data, 'TEST001');

        $this->assertCount(2, $records);
    }

    public function test_minimal_fields(): void
    {
        $data = "1001\t2024-03-15 08:30:00";
        $records = $this->parser->parseAttendanceRecords($data, 'TEST001');

        $this->assertCount(1, $records);
        $this->assertEquals(0, $records[0]->status);
        $this->assertEquals(0, $records[0]->verifyMode);
        $this->assertEquals('', $records[0]->workCode);
    }

    // ---------------------------------------------------------------
    // KV Pair Parsing
    // ---------------------------------------------------------------

    public function test_parse_kv_pairs_newline_separated(): void
    {
        $data = "FWVersion=Ver 8.1.1\nDeviceName=TestDevice\nIPAddress=192.168.1.100";
        $result = $this->parser->parseKVPairs($data, "\n");

        $this->assertEquals('Ver 8.1.1', $result['FWVersion']);
        $this->assertEquals('TestDevice', $result['DeviceName']);
        $this->assertEquals('192.168.1.100', $result['IPAddress']);
    }

    public function test_parse_kv_pairs_with_tilde_transform(): void
    {
        $data = "~DeviceName=Test,~FWVersion=1.0,~MACAddress=AA:BB:CC";
        $result = $this->parser->parseKVPairs($data, ',', [AttendanceParser::class, 'trimTildePrefix']);

        $this->assertEquals('Test', $result['DeviceName']);
        $this->assertEquals('1.0', $result['FWVersion']);
    }

    // ---------------------------------------------------------------
    // User Record Parsing
    // ---------------------------------------------------------------

    public function test_parse_user_records(): void
    {
        $data = "PIN=1\tName=John Doe\tPrivilege=0\tCard=12345\tPassword=pass";
        $records = $this->parser->parseUserRecords($data, 'TEST001');

        $this->assertCount(1, $records);
        $this->assertEquals('1', $records[0]->pin);
        $this->assertEquals('John Doe', $records[0]->name);
        $this->assertEquals(0, $records[0]->privilege);
        $this->assertEquals('12345', $records[0]->card);
        $this->assertEquals('pass', $records[0]->password);
    }

    public function test_skip_user_record_without_pin(): void
    {
        $data = "Name=John Doe\tPrivilege=0";
        $records = $this->parser->parseUserRecords($data, 'TEST001');

        $this->assertCount(0, $records);
    }

    // ---------------------------------------------------------------
    // Command Result Parsing
    // ---------------------------------------------------------------

    public function test_parse_single_command_result(): void
    {
        $body = 'ID=1&Return=0&CMD=INFO';
        $results = $this->parser->parseCommandResults($body, 'TEST001');

        $this->assertCount(1, $results);
        $this->assertEquals(1, $results[0]->id);
        $this->assertEquals(0, $results[0]->returnCode);
        $this->assertEquals('INFO', $results[0]->command);
        $this->assertTrue($results[0]->isSuccess());
    }

    public function test_parse_batched_command_results(): void
    {
        $body = "ID=1&Return=0&CMD=DATA\nID=2&Return=0&CMD=DATA";
        $results = $this->parser->parseCommandResults($body, 'TEST001');

        $this->assertCount(2, $results);
        $this->assertEquals(1, $results[0]->id);
        $this->assertEquals(2, $results[1]->id);
    }

    public function test_parse_failed_command_result(): void
    {
        $body = 'ID=5&Return=-1&CMD=CHECK';
        $results = $this->parser->parseCommandResults($body, 'TEST001');

        $this->assertCount(1, $results);
        $this->assertEquals(-1, $results[0]->returnCode);
        $this->assertFalse($results[0]->isSuccess());
    }

    public function test_parse_shell_multiline_format(): void
    {
        $body = "ID=32\nReturn=0\nCMD=Shell\nContent=some output";
        $results = $this->parser->parseCommandResults($body, 'TEST001');

        $this->assertCount(1, $results);
        $this->assertEquals(32, $results[0]->id);
        $this->assertEquals('Shell', $results[0]->command);
    }
}
