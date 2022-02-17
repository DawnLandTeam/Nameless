<?php
/**
 * Report creation class
 *
 * @package NamelessMC\Misc
 * @author Samerton
 * @version 2.0.0-pr8
 * @license MIT
 */
class Report {

    private DB $_db;

    public function __construct() {
        $this->_db = DB::getInstance();
    }

    /**
     * Create a report.
     *
     * @param Language $language Language to use for messages.
     * @param User $user_reporting User making the report.
     * @param User $reported_user User being reported.
     * @param array $data Array containing report data.
     */
    public function create(Language $language, User $user_reporting, User $reported_user, array $data): void {
        // Insert into database
        if (!$this->_db->insert('reports', $data)) {
            throw new RuntimeException('There was a problem creating the report.');
        }

        $id = $this->_db->lastId();

        // Alert moderators
        $moderator_groups = DB::getInstance()->selectQuery('SELECT id FROM nl2_groups WHERE permissions LIKE \'%"modcp.reports":1%\'')->results();

        if (count($moderator_groups)) {
            $groups = '(';
            foreach ($moderator_groups as $group) {
                if (is_numeric($group->id)) {
                    $groups .= ((int)$group->id) . ',';
                }
            }
            $groups = rtrim($groups, ',') . ')';

            $moderators = DB::getInstance()->selectQuery('SELECT DISTINCT(nl2_users.id) AS id FROM nl2_users LEFT JOIN nl2_users_groups ON nl2_users.id = nl2_users_groups.user_id WHERE group_id in ' . $groups)->results();

            if (count($moderators)) {
                foreach ($moderators as $moderator) {
                    Alert::create($moderator->id, 'report', ['path' => 'core', 'file' => 'moderator', 'term' => 'report_alert'], ['path' => 'core', 'file' => 'moderator', 'term' => 'report_alert'], URL::build('/panel/users/reports/', 'id=' . $id));
                }
            }
        }

        EventHandler::executeEvent('createReport', [
            'username' => $data['reported_mcname'],
            'content' => str_replace('{x}', $user_reporting->username, $language->get('general', 'reported_by')),
            'content_full' => $data['report_reason'],
            'avatar_url' => $data['reported_id'] ? $reported_user->getAvatar() : Util::getAvatarFromUUID($data['reported_uuid']),
            'title' => $language->get('general', 'view_report'),
            'url' => rtrim(Util::getSelfURL(), '/') . URL::build('/panel/users/reports/', 'id=' . $id)
        ]);
    }
}