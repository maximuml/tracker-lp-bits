<?php
class Attendance
{
    protected $userid;
    protected $curdate;
    public function __construct($userid){
        $this->userid = $userid;
        $this->curdate = date('Y-m-d');
        $this->cachename = sprintf('attendance_%u_%s', $this->userid, $this->curdate);
    }

    public function check($flush = false)
    {
        global $Cache;
        if($flush || ($row = $Cache->get_value($this->cachename)) === false){
            $res = \Nexus\Database\NexusDB::select(sprintf('SELECT * FROM `attendance` WHERE `uid` = %u AND DATE(`added`) = %s', $this->userid, \App\Support\LegacyDb::escape($this->curdate.' 00:00:00')));
            $row = count($res) ? mysql_fetch_assoc($res) : array();
            $Cache->cache_value($this->cachename, $row, 600);
        }
        return empty($row) ? false : $row;
    }

    public function attend($initial = 10, $step = 5, $maximum = 2000, $continous = array())
    {
        do_log(json_encode(func_get_args()));
        if($this->check(true)) return false;
        $res = \Nexus\Database\NexusDB::select(sprintf('SELECT id, DATEDIFF(%s, `added`) AS diff, `days`, `total_days` FROM `attendance` WHERE `uid` = %u ORDER BY `id` DESC LIMIT 1', \App\Support\LegacyDb::escape($this->curdate), $this->userid));
        $doUpdate = count($res);
        if ($doUpdate) {
            $row = $res ? array_values((array) $res[0]) : null;
            do_log("uid: {$this->userid}, row: " . json_encode($row));
        } else {
            $row = [0, 0, 0, 0];
        }
        list($id, $datediff, $days, $totalDays) = $row;
        $points = min($initial + $step * $days, $maximum);
        $cdays = $datediff == 1 ? ++$days : 1;
        if($cdays > 1){
            krsort($continous);
            foreach($continous as $sday => $svalue){
                if($cdays >= $sday){
                    $points += $svalue;
                    break;
                }
            }
        }
//        sql_query(sprintf('INSERT INTO `attendance` (`uid`,`added`,`points`,`days`) VALUES (%u, %s, %u, %u)', $this->userid, sqlesc(date('Y-m-d H:i:s')), $points, $cdays)) or sqlerr(__FILE__, __LINE__);
        if ($doUpdate) {
            $sql = sprintf(
                'UPDATE `attendance` set added = %s, points = %s, days = %s, total_days= %s where id = %s limit 1',
                \App\Support\LegacyDb::escape(date('Y-m-d H:i:s')), $points, $cdays, $totalDays + 1, $id
            );
        } else {
            $sql = sprintf(
                'INSERT INTO `attendance` (`uid`, `added`, `points`, `days`, `total_days`) VALUES (%u, %s, %u, %u, %u)',
                $this->userid, \App\Support\LegacyDb::escape(date('Y-m-d H:i:s')), $points, $cdays, $totalDays + 1
            );
        }
        do_log(sprintf('uid: %s, date: %s, doUpdate: %s, sql: %s', $this->userid, $this->curdate, $doUpdate, $sql), 'notice');
        \Nexus\Database\NexusDB::getInstance()->query($sql);
        KPS('+', $points, $this->userid);
        global $Cache;
        $Cache->delete_value($this->cachename);
        return array(++$totalDays, $cdays, $points);
    }
}
