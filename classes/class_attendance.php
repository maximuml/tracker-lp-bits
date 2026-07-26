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
            $record = \App\Models\Attendance::query()
                ->where('uid', $this->userid)
                ->whereDate('added', $this->curdate)
                ->first();
            $row = $record ? $record->toArray() : array();
            $Cache->cache_value($this->cachename, $row, 600);
        }
        return empty($row) ? false : $row;
    }

    public function attend($initial = 10, $step = 5, $maximum = 2000, $continous = array())
    {
        do_log(json_encode(func_get_args()));
        if($this->check(true)) return false;
        $record = \App\Models\Attendance::query()
            ->select('id', \Nexus\Database\NexusDB::raw("DATEDIFF('{$this->curdate}', `added`) AS diff"), 'days', 'total_days')
            ->where('uid', $this->userid)
            ->orderBy('id', 'desc')
            ->first();
        $doUpdate = ! empty($record);
        if ($doUpdate) {
            $row = $record->toArray();
            $id = $row['id'];
            $datediff = $row['diff'];
            $days = $row['days'];
            $totalDays = $row['total_days'];
            do_log("uid: {$this->userid}, row: " . json_encode($row));
        } else {
            $id = 0;
            $datediff = 0;
            $days = 0;
            $totalDays = 0;
        }
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
        $now = date('Y-m-d H:i:s');
        if ($doUpdate) {
            \App\Models\Attendance::query()->where('id', $id)->update([
                'added' => $now,
                'points' => $points,
                'days' => $cdays,
                'total_days' => $totalDays + 1,
            ]);
        } else {
            \App\Models\Attendance::query()->insert([
                'uid' => $this->userid,
                'added' => $now,
                'points' => $points,
                'days' => $cdays,
                'total_days' => $totalDays + 1,
            ]);
        }
        do_log(sprintf('uid: %s, date: %s, doUpdate: %s', $this->userid, $this->curdate, $doUpdate), 'notice');
        KPS('+', $points, $this->userid);
        global $Cache;
        $Cache->delete_value($this->cachename);
        return array(++$totalDays, $cdays, $points);
    }
}
