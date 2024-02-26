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

namespace notificationscondition_forumnoreply;

use local_notificationsagent\evaluationcontext;
use local_notificationsagent\notificationconditionplugin;

class forumnoreply extends notificationconditionplugin {

    /** @var UI ELEMENTS */
    public const NAME = 'forumnoreply';
    public const UNUSED_FORUMS = ['Announcements'];

    public function get_title() {
        return get_string('conditiontext', 'notificationscondition_forumnoreply');
    }

    public function get_elements() {
        return ['[FFFF]', '[TTTT]'];
    }

    public function get_subtype() {
        return get_string('subtype', 'notificationscondition_forumnoreply');
    }

    /** Evaluates this condition using the context variables or the system's state and the complementary flag.
     *
     * @param evaluationcontext $context  |null collection of variables to evaluate the condition.
     *                                    If null the system's state is used.
     *
     * @return bool true if the condition is true, false otherwise.
     */
    public function evaluate(evaluationcontext $context): bool {
        global $DB;

        $courseid = $context->get_courseid();
        $userid = $context->get_userid();
        $pluginname = $this->get_subtype();

        $meetcondition = false;
        $conditionid = $this->get_id();

        $timeaccess = $context->get_timeaccess();

        $time = $DB->get_field(
            'notificationsagent_cache',
            'timestart',
            ['conditionid' => $conditionid, 'courseid' => $courseid, 'userid' => $userid, 'pluginname' => $pluginname],
        );

        if (empty ($time)) {
            return false;
        }

        ($timeaccess >= $time) ? $meetcondition = true : $meetcondition = false;
        return $meetcondition;
    }

    /** Estimate next time when this condition will be true. */
    public function estimate_next_time(evaluationcontext $context) {
        return null;
    }

    public function get_ui($mform, $id, $courseid, $type) {
        $this->get_ui_title($mform, $id, $type);

        // ListForums.
        $listforums = get_coursemodules_in_course('forum', $courseid);

        $forums = [];

        foreach ($listforums as $forum) {
            $forumname = $forum->name;
            if (!in_array($forumname, self::UNUSED_FORUMS)) {
                $forums[$forum->id] = $forumname;
            }
        }

        $element = $mform->createElement(
            'select',
            $this->get_name_ui($id, self::UI_ACTIVITY),
            get_string(
                'editrule_condition_activity', 'notificationscondition_activitysinceend',
                ['typeelement' => '[FFFF]']
            ),
            $forums
        );

        $this->get_ui_select_date($mform, $id, $type);
        $mform->insertElementBefore($element, 'new' . $type . '_group');
    }

    public function check_capability($context) {
        return has_capability('local/notificationsagent:forumnoreply', $context);
    }

    /**
     * @param array $params
     *
     * @return mixed
     */
    protected function convert_parameters($id, $params) {
        $params = (array) $params;
        $activity = $params[$this->get_name_ui($id, self::UI_ACTIVITY)] ?? 0;
        $timevalues = [
            'days' => $params[$this->get_name_ui($id, self::UI_DAYS)] ?? 0,
            'hours' => $params[$this->get_name_ui($id, self::UI_HOURS)] ?? 0,
            'minutes' => $params[$this->get_name_ui($id, self::UI_MINUTES)] ?? 0,
            'seconds' => $params[$this->get_name_ui($id, self::UI_SECONDS)] ?? 0,
        ];
        $timeinseconds = ($timevalues['days'] * 24 * 60 * 60) + ($timevalues['hours'] * 60 * 60)
            + ($timevalues['minutes'] * 60) + $timevalues['seconds'];

        $this->set_parameters(json_encode([self::UI_TIME => $timeinseconds, self::UI_ACTIVITY => (int) $activity]));
        return $this->get_parameters();
    }

    public function process_markups(&$content, $courseid, $options = null) {
        $jsonparams = json_decode($this->get_parameters());

        // Check if activity is found, if is not, return [FFFF].
        $activityname = '[FFFF]';
        $cmid = $jsonparams->{self::UI_ACTIVITY};
        if ($cmid && $forum = get_fast_modinfo($courseid)->get_cm($cmid)) {
            $activityname = $forum->name;
        }

        $paramstoteplace = [$activityname, to_human_format($jsonparams->{self::UI_TIME}, true)];

        $humanvalue = str_replace($this->get_elements(), $paramstoteplace, $this->get_title());

        array_push($content, $humanvalue);
    }

    public function is_generic() {
        return false;
    }

    public function set_default($form, $id) {
        $params = $this->set_default_select_date($id);
        $form->set_data($params);
    }

}
