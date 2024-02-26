<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.
// Project implemented by the \"Recovery, Transformation and Resilience Plan.
// Funded by the European Union - Next GenerationEU\".
//
// Produced by the UNIMOODLE University Group: Universities of
// Valladolid, Complutense de Madrid, UPV/EHU, León, Salamanca,
// Illes Balears, Valencia, Rey Juan Carlos, La Laguna, Zaragoza, Málaga,
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos.

/**
 * Version details
 *
 * @package    local_notificationsagent
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     ISYC <soporte@isyc.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_notificationsagent;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . "/local/notificationsagent/classes/rule.php");
require_once($CFG->dirroot . "/local/notificationsagent/classes/evaluationcontext.php");
require_once($CFG->dirroot . "/local/notificationsagent/classes/notificationsagent.php");

/**
 * @group notificationsagent
 */
class notificationsagent_rule_test extends \advanced_testcase {

    private static $rule;
    private static $user;
    private static $course;
    private static $cmtest;
    public const COURSE_DATESTART = 1704099600; // 01/01/2024 10:00:00.
    public const COURSE_DATEEND = 1706605200; // 30/01/2024 10:00:00,
    public const CM_DATESTART = 1704099600; // 01/01/2024 10:00:00,
    public const CM_DATEEND = 1705741200; // 20/01/2024 10:00:00,
    public const USER_FIRSTACCESS = 1704099600;
    public const USER_LASTACCESS = 1706605200;
    public const CMID = 246000;

    final public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $rule = new rule();
        self::$rule = $rule;
        self::$user = self::getDataGenerator()->create_user();
        self::$course = self::getDataGenerator()->create_course(
            ([
                'startdate' => self::COURSE_DATESTART,
                'enddate' => self::COURSE_DATEEND,
            ])
        );
        self::getDataGenerator()->create_user_course_lastaccess(self::$user, self::$course, self::USER_LASTACCESS);

        $quizgenerator = self::getDataGenerator()->get_plugin_generator('mod_quiz');
        self::$cmtest = $quizgenerator->create_instance([
            'name' => 'Quiz unittest',
            'course' => self::$course->id,
            "timeopen" => self::CM_DATESTART,
            "timeclose" => self::CM_DATEEND,
        ]);
    }

    /**
     * @covers       \local_notificationsagent\rule::evaluate
     * @dataProvider dataprovider
     */
    public function test_evaluate(int $date, array $conditiondata, array $exceptiondata, bool $expected) {
        global $DB, $USER;

        $dataform = new \StdClass();
        $dataform->title = "Rule Test";
        $dataform->type = 1;
        $dataform->courseid = self::$course->id;
        $dataform->timesfired = 2;
        $dataform->runtime_group = ['runtime_days' => 5, 'runtime_hours' => 0, 'runtime_minutes' => 0];
        $USER->id = self::$user->id;
        $ruleid = self::$rule->create($dataform);
        self::$rule->set_id($ruleid);
        self::$cmtest->cmid = self::CMID;
        $userid = self::$user->id;
        $courseid = self::$course->id;

        // Context.
        $context = new evaluationcontext();
        $context->set_userid($userid);
        $context->set_courseid($courseid);
        $context->set_timeaccess($date);

        foreach ($conditiondata as $condition) {
            // Conditions.
            $objdb = new \stdClass();
            $objdb->ruleid = $ruleid;
            $objdb->courseid = $courseid;
            $objdb->type = 'condition';
            $objdb->pluginname = $condition['pluginname'];
            $objdb->parameters = $condition['params'];
            $objdb->cmid = self::CMID;
            // Insert.
            $conditionid = $DB->insert_record('notificationsagent_condition', $objdb);
            $context->set_triggercondition($conditionid);
        }

        foreach ($exceptiondata as $exception) {
            // Conditions.
            $objdb = new \stdClass();
            $objdb->ruleid = $ruleid;
            $objdb->courseid = $courseid;
            $objdb->type = 'condition';
            $objdb->pluginname = $exception['pluginname'];
            $objdb->parameters = $exception['params'];
            $objdb->cmid = self::CMID;
            $objdb->complementary = notificationplugin::COMPLEMENTARY_EXCEPTION;
            // Insert.
            $DB->insert_record('notificationsagent_condition', $objdb);

        }
        $instance = self::$rule::create_instance($ruleid);
        $conditions = $instance->get_conditions();
        $exceptions = $instance->get_exceptions();
        self::$rule->set_conditions($conditions);
        self::$rule->set_exceptions($exceptions);
        $result = self::$rule->evaluate($context);

        $this->assertSame($expected, $result);

    }

    public static function dataprovider(): array {
        return [
            // Evaluate date, conditions, exceptions, expected.
            [
                1704186000, [['pluginname' => 'coursestart', 'params' => '{"time":864000}']],
                [['pluginname' => '', 'params' => '']], false,
            ],
            [
                1705050000, [['pluginname' => 'coursestart', 'params' => '{"time":864000}']],
                [['pluginname' => '', 'params' => '']], true,
            ],
            [
                1705050000, [['pluginname' => 'courseend', 'params' => '{"time":864000}']], [['pluginname' => '', 'params' => '']],
                false,
            ],
            [
                1706173200, [['pluginname' => 'courseend', 'params' => '{"time":864000}']], [['pluginname' => '', 'params' => '']],
                true,
            ],
            [
                1706173200,
                [
                    ['pluginname' => 'coursestart', 'params' => '{"time":864000}'],
                    ['pluginname' => 'courseend', 'params' => '{"time":864000}'],
                ],
                [['pluginname' => '', 'params' => '']], true,
            ],
            [
                1706173200,
                [
                    ['pluginname' => 'coursestart', 'params' => '{"time":864000}'],
                    ['pluginname' => 'courseend', 'params' => '{"time":864000}'],
                ],
                [['pluginname' => 'sessionend', 'params' => '{"time":864000}']], true,
            ],
            [
                1708851600, [['pluginname' => 'sessionend', 'params' => '{"time":86400}']], [['pluginname' => '', 'params' => '']],
                true,
            ],
            [
                1708851600, [['pluginname' => 'sessionend', 'params' => '{"time":86400}']],
                [['pluginname' => 'coursestart', 'params' => '{"time":86400}']], false,
            ],
            [
                1706173200,
                [
                    ['pluginname' => 'coursestart', 'params' => '{"time":864000}'],
                ],
                [
                    ['pluginname' => 'courseend', 'params' => '{"time":864000}'],
                ], false,
            ],
        ];
    }

    /**
     * @covers \local_notificationsagent\rule::create
     * @covers \local_notificationsagent\rule::create_instance
     * @covers \local_notificationsagent\rule::get_conditions
     * @covers \local_notificationsagent\rule::get_exceptions
     * @covers \local_notificationsagent\rule::get_actions
     * @covers \local_notificationsagent\rule::set_createdby
     * @covers \local_notificationsagent\rule::get_template
     * @covers \local_notificationsagent\rule::get_name
     * @covers \local_notificationsagent\rule::get_id
     * @covers \local_notificationsagent\rule::set_default_context
     * @covers \local_notificationsagent\rule::get_default_context
     *
     */
    public function test_create() {
        global $DB, $USER;
        $USER->id = self::$user->id;
        // Simulate data from form.
        $dataform = new \StdClass();
        $dataform->title = "Rule Test";
        $dataform->type = 1;
        $dataform->courseid = self::$course->id;
        $dataform->timesfired = 2;
        $dataform->runtime_group = ['runtime_days' => 5, 'runtime_hours' => 0, 'runtime_minutes' => 0];

        $ruleid = self::$rule->create($dataform);
        // Conditions.
        $DB->insert_record(
            'notificationsagent_condition',
            [
                'ruleid' => $ruleid, 'type' => 'condition', 'complementary' => notificationplugin::COMPLEMENTARY_CONDITION,
                'parameters' => '{"time":300,"forum":3}',
                'pluginname' => 'forumnoreply',
            ],
        );
        $DB->insert_record(
            'notificationsagent_condition',
            [
                'ruleid' => $ruleid, 'type' => 'condition', 'complementary' => notificationplugin::COMPLEMENTARY_EXCEPTION,
                'parameters' => '{"time":300}',
                'pluginname' => 'coursestart',
            ],
        );

        $DB->insert_record(
            'notificationsagent_action',
            [
                'ruleid' => $ruleid, 'type' => 'action', 'pluginname' => 'messageagent',
                'parameters' => '{"title":"Friday - {Current_time}","message":" It is friday."}',
            ],
        );

        $instance = self::$rule::create_instance($ruleid);

        $this->assertInstanceOf(rule::class, $instance);
        $this->assertIsNumeric($ruleid);
        $this->assertGreaterThan(0, $ruleid);
        $this->assertSame('Rule Test', $instance->get_name());
        $this->assertSame('1', $instance->get_template());
        $this->assertSame($USER->id, $instance->get_createdby());
        $this->assertEquals(self::$rule->get_id(), $instance->get_id());
        $this->assertNotEmpty($instance->get_conditions());
        $this->assertNotEmpty($instance->get_exceptions());
        $this->assertNotEmpty($instance->get_actions());
        $this->assertSame(self::$course->id, $instance->get_default_context());
    }

    /**
     * @covers       \local_notificationsagent\rule::update
     * @covers       \local_notificationsagent\rule::get_timesfired
     * @covers       \local_notificationsagent\rule::get_runtime
     * @covers       \local_notificationsagent\rule::get_createdby
     * @covers       \local_notificationsagent\rule::get_name
     * @dataProvider updateprovider
     *
     */
    public function test_update($timesfired, $expected) {
        self::setUser(self::$user->id);
        // Simulate data from form.
        $dataform = new \StdClass();
        $dataform->title = "Rule Test";
        $dataform->type = 1;
        $dataform->courseid = self::$course->id;
        $dataform->timesfired = 2;
        $dataform->runtime_group = ['runtime_days' => 5, 'runtime_hours' => 0, 'runtime_minutes' => 0];

        $ruleid = self::$rule->create($dataform);
        $instance = self::$rule::create_instance($ruleid);

        // Simulate data from edit form.
        $dataupdate = new \StdClass();
        $dataupdate->title = "Rule Test update";
        $dataupdate->type = 1;
        $dataupdate->courseid = self::$course->id;
        $dataupdate->timesfired = $timesfired;
        $dataupdate->runtime_group = ['runtime_days' => 1, 'runtime_hours' => 0, 'runtime_minutes' => 0];

        // Test update.
        $instance->update($dataupdate);

        $this->assertSame('Rule Test update', $instance->get_name());
        $this->assertSame($expected, $instance->get_timesfired());
        $this->assertSame(86400, $instance->get_runtime());
        $this->assertSame(self::$user->id, $instance->get_createdby());

    }

    public static function updateprovider(): array {
        return [
            [18, 18],
            [0, 1],
            [null, 1],
        ];
    }

    /**
     * @covers \local_notificationsagent\rule::delete
     * @covers \local_notificationsagent\rule::before_delete
     * @covers \local_notificationsagent\rule::delete_conditions
     * @covers \local_notificationsagent\rule::delete_actions
     * @covers \local_notificationsagent\rule::delete_context
     *
     */
    public function test_delete() {
        global $DB, $USER;
        $USER->id = self::$user->id;
        // Simulate data from form.
        $dataform = new \StdClass();
        $dataform->title = "Rule Test";
        $dataform->type = 1;
        $dataform->courseid = self::$course->id;
        $dataform->timesfired = 2;
        $dataform->runtime_group = ['runtime_days' => 5, 'runtime_hours' => 0, 'runtime_minutes' => 0];

        $ruleid = self::$rule->create($dataform);

        // Conditions.
        $conditionid = $DB->insert_record(
            'notificationsagent_condition',
            [
                'ruleid' => $ruleid, 'type' => 'condition', 'complementary' => notificationplugin::COMPLEMENTARY_CONDITION,
                'parameters' => '{"time":300,"forum":3}',
                'pluginname' => 'forumnoreply',
            ],
        );
        $DB->insert_record(
            'notificationsagent_condition',
            [
                'ruleid' => $ruleid, 'type' => 'condition', 'complementary' => notificationplugin::COMPLEMENTARY_EXCEPTION,
                'parameters' => '{"time":300}',
                'pluginname' => 'coursestart',
            ],
        );

        $DB->insert_record(
            'notificationsagent_action',
            [
                'ruleid' => $ruleid, 'type' => 'action', 'pluginname' => 'messageagent',
                'parameters' => '{"title":"Friday - {Current_time}","message":" It is friday."}',
            ],
        );

        $DB->insert_record(
            'notificationsagent_cache',
            [
                'ruleid' => $ruleid, 'pluginname' => 'forumnoreply',
                'courseid' => self::$course->id, 'userid' => self::$user->id,
                'timestart' => time(), 'conditionid' => $conditionid,
            ],
        );

        $DB->insert_record(
            'notificationsagent_cache',
            [
                'ruleid' => $ruleid, 'courseid' => self::$course->id,
                'userid' => self::$user->id,
                'startdate' => time(), 'conditionid' => $conditionid,
            ],
        );

        $DB->insert_record(
            'notificationsagent_launched',
            [
                'ruleid' => $ruleid, 'courseid' => self::$course->id,
                'userid' => self::$user->id,
                'timesfired' => 2, 'timecreated' => time(),
                'timemodified' => time(),
            ],
        );

        $DB->insert_record(
            'notificationsagent_context',
            [
                'ruleid' => $ruleid, 'contextid' => 50,
                'objectid' => 2,
            ],
        );

        $instance = self::$rule::create_instance($ruleid);
        $this->assertInstanceOf(rule::class, $instance);
        $instance->before_delete();
        $this->assertTrue($instance->delete());
        $cache = $DB->get_record('notificationsagent_cache', ['conditionid' => $conditionid]);
        $trigger = $DB->get_record('notificationsagent_triggers', ['conditionid' => $conditionid]);
        $launched = $DB->get_record('notificationsagent_launched', ['ruleid' => self::$rule->get_id()]);
        $context = $DB->get_record('notificationsagent_launched', ['ruleid' => self::$rule->get_id()]);

        $this->assertFalse($cache);
        $this->assertFalse($trigger);
        $this->assertFalse($launched);
        $this->assertFalse($context);

    }

}