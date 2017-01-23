<?php

class pocketlistsTodoAction extends waViewAction
{
    public function execute()
    {
        $month_count = 3;

        $timezone = wa()->getUser()->getTimezone();
        // Y-m-d -> 2011-01-01
        $month_date = waRequest::get("month");
        if (!$month_date) {
            $month_date = waDateTime::date("Y-m", null, $timezone);
        } elseif ($month_date <= "1970" || $month_date >= "2033" || !strtotime($month_date)) {
            $this->redirect("?action=todo");
        }
        $month_date = strtotime($month_date);

        // get pocket dots
        $im = new pocketlistsItemModel();

        // get all due or priority or assigned to me items
        $items = $im->getToDo(wa()->getUser()->getId());
        $list_colors = array();
        // completed items
        foreach ($items[1] as $item) {
            $list_colors[date("Y-m-d", strtotime($item['complete_datetime']))]['gray'][] = $item['id'];
        }
        foreach ($items[0] as $item) {
            if ($item['due_datetime'] || $item['due_date']) {
                $due_date = date("Y-m-d", strtotime($item['due_date'] ? $item['due_date'] : $item['due_datetime']));
                $item['list_color'] = $item['list_color'] ? $item['list_color'] : 'blue';
                $list_colors[$due_date]['color'][$item['list_color']][] = $item['id'];
            }
        }


//        $items = $im->getCompleted(
//            wa()->getUser()->getId(),
//            array('after' => date('Y-m-d', $current_date_start), 'before' => date('Y-m-d', $date_end))
//        );
//        $list_colors = array();
//        foreach ($items as $item) {
//            $list_colors[date("Y-m-d", strtotime($item['complete_datetime']))][$item['list_color']] = 1;
//        }

        $days = array();
        for ($i = 0; $i < $month_count; $i++) { // 3 month
            $days_count = date("t", $month_date);
            $first_day = date("w", $month_date);
            $last_day = date("w", strtotime(date("Y-m-{$days_count}", $month_date)));

            // first day is 'Sunday'
            if (waLocale::getFirstDay() == 7) {
                $first_day += 1;
                $last_day += 1;
            }
            $first_day = ($first_day == 0) ? 6 : $first_day - 1;
            $last_day = ($last_day == 0) ? 0 : 7 - $last_day;
            $date_start = strtotime("-" . $first_day . " days", $month_date);
            $date_end = strtotime("+" . ($days_count + $last_day) . " days", $month_date);

            $current_date_start = $date_start;
            $year = date("Y", $month_date);
            $month_name = date("F", $month_date);
            $month_num = (int)date("n", $month_date);
            $days[$year][$month_name] = array(
                'weeks' => array(),
                'num' => $month_num
            );


            // completed for this date period
//            $items = $im->getCompleted(
//                wa()->getUser()->getId(),
//                array('after' => date('Y-m-d', $current_date_start), 'before' => date('Y-m-d', $date_end))
//            );
//            $list_colors = array();
//            foreach ($items as $item) {
//                $list_colors[date("Y-m-d", strtotime($item['complete_datetime']))][$item['list_color']] = 1;
//            }

            do {
                $week = (int)date("W", $current_date_start);
                $day = (int)date("w", $current_date_start);

                if (waLocale::getFirstDay() == 7 && $day == 0) {
                    $week = (int)date("W", strtotime("+1 week", $current_date_start));
                }

                if (!isset($days[$year][$month_name]['weeks'][$week])) {
                    $days[$year][$month_name]['days'][$week] = array();
                }
                $date_date = date("Y-m-d", $current_date_start);
                $hide_other_month_date = false;
                $current_month = date("n", $current_date_start);
                if ($month_num != $current_month) { // hide other month days
                    $hide_other_month_date = true;
                }
                if ($i == 0 && $current_date_start < $month_date) { // but show dates before first month
                    $hide_other_month_date = false;
                }
                if ($i == ($month_count - 1) && $current_date_start > $month_date) { // and after last month
                    $hide_other_month_date = false;
                }
                $days[$year][$month_name]['weeks'][$week][$day] = array(
                    "date" => array(
                        'day' => date("j", $current_date_start),
                        'month' => $current_month,
                        'date' => $date_date,
                    ),
                    "hide" => $hide_other_month_date,
                    'lists' => array(
                        'color' => isset($list_colors[$date_date]['color']) ?
                            $list_colors[$date_date]['color'] : array(),
                        'gray' => isset($list_colors[$date_date]['gray']) ?
                            $list_colors[$date_date]['gray'] : array()
                    ),
//                        isset($list_colors[$date_date]) ? array_keys($list_colors[$date_date]) : array()
                );
                $current_date_start = strtotime("+1 days", $current_date_start);
            } while ($date_end > $current_date_start);

            $month_date = strtotime("+1 month", $month_date);
        }

        $this->view->assign("days", $days);

        $this->view->assign("week_first_sunday", waLocale::getFirstDay() == 7);
        $this->view->assign("current_month", date("n", $month_date));
        $this->view->assign("current_year", date("Y", $month_date));
        $this->view->assign("prev_month", date("Y-m", strtotime("-1 month", $month_date)));
        $this->view->assign("next_month", date("Y-m", strtotime("+1 month", $month_date)));

        // cast to user timezone
        $this->view->assign("today", waDateTime::date("j", null, $timezone));
        $this->view->assign("today_month", waDateTime::date("n", null, $timezone));
    }
}
