<?php
// This file is part of Moodle - http://moodle.org/
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
defined('MOODLE_INTERNAL') || die();
global $CFG, $SESSION;
require_once($CFG->dirroot . "/local/notificationsagent/classes/notificationactivityconditionplugin.php");
use local_notificationsagent\notification_activityconditionplugin;
use local_notificationsagent\EvaluationContext;
class notificationsagent_condition_activitystudentend extends notification_activityconditionplugin {

    public function get_description() {
        return [
            'title' => self::get_title(),
            'elements' => self::get_elements(),
            'name' => self::get_subtype(),
        ];
    }

    public function get_title() {
        return get_string('conditiontext', 'notificationscondition_activitystudentend');
    }

    public function get_elements() {
        return ['[TTTT]', '[AAAA]'];
    }

    public function get_subtype() {
        return get_string('subtype', 'notificationscondition_activitystudentend');
    }

    /** Evaluates this condition using the context variables or the system's state and the complementary flag.
     *
     * @param EvaluationContext $context |null collection of variables to evaluate the condition.
     *                                    If null the system's state is used.
     *
     * @return bool true if the condition is true, false otherwise.
     */
    public function evaluate(EvaluationContext $context): bool {
        global $DB;
        $courseid = $context->get_courseid();
        $userid = $context->get_userid();
        $pluginname = $this->get_subtype();
        $conditionid = $this->get_id();
        $timeaccess = $context->get_timeaccess();
        $params = json_decode($context->get_params());
        $meetcondition = false;

        // Timestart es el tiempo de primer acceso más time.
        $firstacces = $DB->get_field('notificationsagent_cache',
            'timestart', ['conditionid' => $conditionid, 'courseid' => $courseid, 'userid' => $userid, 'pluginname' => $pluginname],
        );

        if (!empty($firstacces)) {
            ($timeaccess > $firstacces) ? $meetcondition = true : $meetcondition = false;
            // La regla se hizo después de que el usuario entrara en el curso.
        } else {
            // Check own plugin table.
            $firstacces = $DB->get_field('notificationsagent_cmview',
                'firstaccess', ['courseid' => $courseid, 'userid' => $userid, 'idactivity' => $params->activity],
            );
            // El usuario no ha desencadenado nunca un course_view ¿Comprobar log_standard_log?.
            if (empty($firstacces)) {
                return $meetcondition;
            }

            ($timeaccess > $firstacces + $params->time) ? $meetcondition = true : $meetcondition = false;

        }

        return $meetcondition;
    }

    /** Estimate next time when this condition will be true. */
    public function estimate_next_time(EvaluationContext $context) {
        return null;
    }

    /** Returns the name of the plugin
     *
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', 'notificationscondition_activitystudentend');
    }

    public function get_ui($mform, $id, $courseid, $exception) {
        global $SESSION;
        $valuesession = 'id_'.$this->get_subtype().'_'.$this->get_type().$exception.$id.'_time_'.$this->get_type().$exception.$id;

        $mform->addElement('hidden', 'pluginname'.$this->get_type().$exception.$id, $this->get_subtype());
        $mform->setType('pluginname'.$this->get_type().$exception.$id, PARAM_RAW );
        $mform->addElement('hidden', 'type'.$this->get_type().$exception.$id, $this->get_type().$id);
        $mform->setType('type'.$this->get_type().$exception.$id, PARAM_RAW );

        $timegroup = [];
        // Days.
        $timegroup[] =& $mform->createElement('float', 'condition'.$exception.$id.'_days', '',
            [
                'class' => 'mr-2', 'size' => '7', 'maxlength' => '3',
                'placeholder' => get_string('condition_days', 'local_notificationsagent'),
                'oninput' => 'this.value = this.value.replace(/[^0-9]/g, "").replace(/(\..*)\./g, "$1")',
                'value' => $SESSION->NOTIFICATIONS['FORMDEFAULT'][$valuesession . '_days'] ?? null,
            ]
        );

        // Hours.
        $timegroup[] =& $mform->createElement('float', 'condition'.$exception.$id.'_hours', '',
            [
                'class' => 'mr-2', 'size' => '7', 'maxlength' => '3',
                   'placeholder' => get_string('condition_hours', 'local_notificationsagent'),
                   'oninput' => 'this.value = this.value.replace(/[^0-9]/g, "").replace(/(\..*)\./g, "$1")',
                   'value' => $SESSION->NOTIFICATIONS['FORMDEFAULT'][$valuesession . '_hours'] ?? null,
            ]
        );
        // Minutes.
        $timegroup[] =& $mform->createElement('float', 'condition'.$exception.$id.'_minutes', '',
            [
                'class' => 'mr-2', 'size' => '7', 'maxlength' => '2',
                'placeholder' => get_string('condition_minutes', 'local_notificationsagent'),
            'oninput' => 'this.value = this.value.replace(/[^0-9]/g, "").replace(/(\..*)\./g, "$1")',
            'value' => $SESSION->NOTIFICATIONS['FORMDEFAULT'][$valuesession . '_minutes'] ?? null,
            ]
        );
        // Seconds.
        $timegroup[] =& $mform->createElement('float', 'condition'.$exception.$id.'_seconds', '',
            [
                'class' => 'mr-2', 'size' => '7', 'maxlength' => '2',
                'placeholder' => get_string('condition_seconds', 'local_notificationsagent'),
                'oninput' => 'this.value = this.value.replace(/[^0-9]/g, "").replace(/(\..*)\./g, "$1")',
                'value' => $SESSION->NOTIFICATIONS['FORMDEFAULT'][$valuesession . '_seconds'] ?? null,
            ]
        );
        // GroupTime.
        $mform->addGroup($timegroup, $this->get_subtype().'_condition'.$exception.$id.'_time',
            get_string('editrule_condition_element_time', 'notificationscondition_sessionstart',
                ['typeelement' => '[TTTT]']
            ));
        $mform->addGroupRule(
            $this->get_subtype() . '_condition' . $exception . $id . '_time', '- You must supply a value here.', 'required'
        );
        // Activity.
        $listactivities = [];
        $modinfo = get_fast_modinfo($courseid);
        foreach ($modinfo->get_cms() as $cm) {
            $listactivities[$cm->id] = format_string($cm->name);
        }
        if (empty($listactivities)) {
            $listactivities['0'] = 'AAAA';
        }
        asort($listactivities);
        $mform->addElement(
            'select', $this->get_subtype().'_' .$this->get_type() .$exception.$id.'_activity',
            get_string(
                'editrule_condition_activity', 'notificationscondition_activitystudentend',
                ['typeelement' => '[AAAA]']
            ),
            $listactivities
        );
        if (!empty($SESSION->NOTIFICATIONS['FORMDEFAULT']['id_'.$this->get_subtype().'_'
                   .$this->get_type().$exception.$id.'_activity'])) {
            $mform->setDefault($this->get_subtype().'_' .$this->get_type() .$exception.$id.'_activity',
            $SESSION->NOTIFICATIONS['FORMDEFAULT']['id_'.$this->get_subtype().'_'.$this->get_type().$exception.$id.'_activity']);
        }
    }

    public function check_capability($context) {
        return has_capability('local/notificationsagent:activitystudentend', $context);
    }

    /**
     * @param array $params
     *
     * @return mixed
     */
    public function convert_parameters($params) {
        $timeunits = ['days', 'hours', 'minutes', 'seconds'];
        $timevalues = [
            'days' => 0,
            'hours' => 0,
            'minutes' => 0,
            'seconds' => 0,
        ];
        $activity = 0;
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subkey => $subvalue) {
                    foreach ($timeunits as $unit) {
                        if (strpos($subkey, $unit) !== false) {
                            $timevalues[$unit] = $subvalue;
                        }
                    }
                }
            } else if (strpos($key, "activity") !== false) {
                $activity = $value;
            }
        }
        $timeinseconds = ($timevalues['days'] * 24 * 60 * 60) + ($timevalues['hours'] * 60 * 60)
            + ($timevalues['minutes'] * 60) + $timevalues['seconds'];
        return json_encode(['time' => $timeinseconds, 'activity' => (int) $activity]);
    }

    public function process_markups(&$content, $params, $courseid, $complementary=null) {
        $activityincourse = false;
        $jsonparams = json_decode($params);

        $modinfo = get_fast_modinfo($courseid);
        $types = $modinfo->get_cms();

        foreach ($types as $type) {
            if ($type->id == $jsonparams->activity) {
                $activityname = $type->name;
                $activityincourse = true;
            }
        }

        // Check if activity is found in course, if is not, return [AAAA].
        if (!$activityincourse) {
            $activityname = '[AAAA]';
        }

        $paramstoreplace = [$this->get_human_time($jsonparams->time), $activityname];
        $humanvalue = str_replace($this->get_elements(), $paramstoreplace, $this->get_title());

        array_push($content, $humanvalue);
    }

    public function is_generic() {
        return false;
    }

    /**
     * Return the module identifier specified in the condition
     * @param object $parameters Plugin parameters
     *
     * @return int|null $cmid Course module id or null
     */
    public function get_cmid($parameters) {
        return $parameters->activity;
    }
}
