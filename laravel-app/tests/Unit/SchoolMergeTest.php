<?php

namespace Tests\Unit;

use App\Support\AnnouncementPersonalization;
use App\Support\SchoolMerge;
use PHPUnit\Framework\TestCase;

class SchoolMergeTest extends TestCase
{
    public function test_st_fredrick_and_st_frederick_are_school_templates()
    {
        $this->assertTrue(SchoolMerge::isSchool('St Fredrick fee notice', '', 'Please pay.'));
        $this->assertTrue(SchoolMerge::isSchool('', 'Fees', 'Your child is at St. Frederick College.'));
        $this->assertTrue(SchoolMerge::isSchool('', '', 'Dear {Parent}, your child {Student} is in {Class}.'));
        $this->assertFalse(SchoolMerge::isSchool('Choir practice', '', 'Dear {name}, practice is at 5.'));
    }

    public function test_customer_fields_fill_parent_student_and_class()
    {
        $person = SchoolMerge::decorate([
            'name' => 'MAIMOUNATOU NA',
            'company_name' => 'ABDUL RAZAK OUMAROU',
            'city' => 'FORM ONE',
            'phone' => '237670703582',
        ]);

        $this->assertSame('MAIMOUNATOU NA', $person['parent']);
        $this->assertSame('ABDUL RAZAK OUMAROU', $person['student']);
        $this->assertSame('FORM ONE', $person['class']);
    }

    public function test_a_labeled_whatsapp_name_is_split_into_parent_student_and_class()
    {
        $person = SchoolMerge::decorate([
            'name' => 'MAIMOUNATOU NA | ABDUL RAZAK OUMAROU | FORM ONE',
            'phone' => '237670703582',
        ]);

        $this->assertSame('MAIMOUNATOU NA', $person['name']);
        $this->assertSame('MAIMOUNATOU NA', $person['parent']);
        $this->assertSame('ABDUL RAZAK OUMAROU', $person['student']);
        $this->assertSame('FORM ONE', $person['class']);
    }

    public function test_blank_markers_are_not_used_as_a_class_or_student()
    {
        $person = SchoolMerge::decorate([
            'name' => 'Ada Lovelace',
            'company_name' => 'N/A',
            'city' => 'NAN',
        ]);

        $this->assertSame('', $person['student']);
        $this->assertSame('', $person['class']);
        $this->assertSame('Ada Lovelace', $person['parent']);
    }

    public function test_a_missing_student_stays_visible_in_the_message()
    {
        $announcement = (object) [
            'personalized' => false,
            'header' => '',
            'reference' => '',
            'body' => "Please note {Student} has not paid.\n\nSincerely.\nSchool Administration.",
            'footer' => '',
        ];

        $message = AnnouncementPersonalization::buildMessage($announcement, [
            'name' => 'Ada Lovelace',
            'phone' => '237600000000',
        ]);

        $this->assertStringContainsString('{Student}', $message);
        $this->assertStringContainsString('Dear *Ada Lovelace*,', $message);
    }

    public function test_the_attached_letter_uses_the_same_parent_student_and_class()
    {
        $announcement = (object) [
            'subject' => 'First installment',
            'header' => "St. Frederick's College, Bamenda",
            'reference' => 'SFC/26/0001',
            'body' => "Dear {Parent},\n\nYour child {Student} who is in {Class} should pay the first installment.",
            'footer' => "Sincerely.\nSchool Administration.",
        ];
        $parts = SchoolMerge::documentParts($announcement, [
            'name' => 'MAIMOUNATOU NA',
            'parent' => 'MAIMOUNATOU NA',
            'student' => 'ABDUL RAZAK OUMAROU',
            'class' => 'FORM ONE',
            'phone' => '237670703582',
        ]);

        $this->assertStringContainsString('Frederick', $parts['heading']);
        $this->assertStringContainsString('Dear MAIMOUNATOU NA', $parts['body']);
        $this->assertStringContainsString('ABDUL RAZAK OUMAROU', $parts['body']);
        $this->assertStringContainsString('FORM ONE', $parts['body']);
        $this->assertStringNotContainsString('{Student}', $parts['body']);
        $this->assertSame('Sincerely.', strtok($parts['footer'], "\n"));
        $this->assertSame('St-Frederick-ABDUL-RAZAK-OUMAROU.pdf', SchoolMerge::fileName([
            'student' => 'ABDUL RAZAK OUMAROU',
        ]));
    }
}
