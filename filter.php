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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Used to display Leeloo LXP content plugins anywhere in Moodle contents.
 *
 * @package    filter_leeloolxp
 * @copyright  filter_leeloolxp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class filter_leeloolxp
 */
class filter_leeloolxp extends moodle_text_filter {

    /**
     * Apply the filter to the text and display Leeloo LXP content plugins instead of shortcode.
     *
     * @param string $text to be processed by the text
     * @param array $options filter options
     * @return string text after processing
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @see filter_manager::apply_filter_chain()
     */
    public function filter($text, array $options = array()) {
        if (strpos($text, '[[LEELOOLXP_') === false) {
            return $text;
        }
        global $CFG, $DB, $PAGE, $USER, $SITE;
        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');

        $pattern = '/\[\[(.*?)\]\]/i';
        preg_match_all($pattern, $text, $regs, PREG_PATTERN_ORDER);

        for ($i = 0; $i < count($regs[1]); $i++) {
            $needreplace = 0;
            if ($regs[1][$i] == 'LEELOOLXP_RECENT_BLOGS') {
                $needreplace = 1;
                $contentplugin = 'tb_blog';
            } else if ($regs[1][$i] == 'LEELOOLXP_CLIENTS') {
                $needreplace = 1;
                $contentplugin = 'tb_clients';
            } else if ($regs[1][$i] == 'LEELOOLXP_COURSES') {
                $needreplace = 1;
                $contentplugin = 'tb_courses';
            } else if ($regs[1][$i] == 'LEELOOLXP_FAQ') {
                $needreplace = 1;
                $contentplugin = 'tb_faq';
            } else if ($regs[1][$i] == 'LEELOOLXP_HIGHLIGHTS') {
                $needreplace = 1;
                $contentplugin = 'tb_headings';
            } else if ($regs[1][$i] == 'LEELOOLXP_LAST_ENTRY') {
                $needreplace = 1;
                $contentplugin = 'tb_latestentry';
            } else if ($regs[1][$i] == 'LEELOOLXP_BENEFITS') {
                $needreplace = 1;
                $contentplugin = 'tb_m_slots';
            } else if ($regs[1][$i] == 'LEELOOLXP_SLIDER') {
                $needreplace = 1;
                $contentplugin = 'tb_slider';
            } else if ($regs[1][$i] == 'LEELOOLXP_TEACHERS') {
                $needreplace = 1;
                $contentplugin = 'tb_teachers';
            } else if ($regs[1][$i] == 'LEELOOLXP_TESTIMONIALS') {
                $needreplace = 1;
                $contentplugin = 'tb_testimonials';
            } else if ($regs[1][$i] == 'LEELOOLXP_TOPCATS') {
                $needreplace = 1;
                $contentplugin = 'tb_top_cats';
            }

            if ($needreplace == 1) {
                if (!file_exists($CFG->dirroot . '/blocks/' . $contentplugin . '/block_' . $contentplugin . '.php')) {
                    $newval = $contentplugin . get_string('block_notinstalled', 'filter_leeloolxp');
                    $text = str_replace($regs[0][$i], $newval, $text);
                    continue;
                }
                require_once($CFG->dirroot . '/blocks/' . $contentplugin . '/block_' . $contentplugin . '.php');

                $blockinstance = $DB->get_record(
                    'block_instances',
                    array('blockname' => $contentplugin),
                    '*',
                    $strictness = IGNORE_MULTIPLE
                );
                if (!$blockinstance) {
                    $newval = $contentplugin . get_string('block_instancenotfound', 'filter_leeloolxp');
                    $text = str_replace($regs[0][$i], $newval, $text);
                    continue;
                }
                $blockclass = 'block_' . $contentplugin;
                $block = new $blockclass();
                $block->_load_instance($blockinstance, $PAGE);
                $content = $block->get_content();

                $contextblock = context_block::instance($blockinstance->id);
                $parentcontext = $contextblock->get_parent_context();
                $blockonfrontpage = ($SITE->id == $parentcontext->instanceid); // Skip enrolment and course capability check.
                if (
                    !has_capability('moodle/block:view', $contextblock)
                    or !$blockonfrontpage and ($parentcontext->contextlevel == CONTEXT_COURSE and !is_enrolled($parentcontext))
                    and ($parentcontext->contextlevel == CONTEXT_COURSE
                        and !has_capability('moodle/course:view', $parentcontext)
                    )
                ) {
                    // This user is not allowed to see this block.
                    if (isset($USER->editing) && $USER->editing) {
                        // Only when editing user can see the message.
                        $newval = $contentplugin . get_string('not_allowed', 'filter_leeloolxp');
                        $text = str_replace($regs[0][$i], $newval, $text);
                        continue;
                    }
                    // Specifically, I do not display any message to avoid confusion among users.
                    $newval = $contentplugin . get_string('not_allowed', 'filter_leeloolxp');
                    $text = str_replace($regs[0][$i], $newval, $text);
                    continue;
                }

                $info = new cached_cm_info();
                $info->name = '';
                if (!isset($content->text) && !isset($block->content->text)) {
                    $newval = $contentplugin . get_string('block_erroradding', 'filter_leeloolxp');
                } else {
                    if (isset($content->text)) {
                        $blockcontent = $content->text;
                    } else {
                        $blockcontent = $block->content->text;
                    }
                    $newval = '<section class="block_' . $contentplugin . '">' . $blockcontent . '</section>';
                }
            } else {
                $newval = $regs[1][$i];
            }

            $text = str_replace($regs[0][$i], $newval, $text);
        }
        return $text;
    }
}
