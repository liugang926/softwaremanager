<?php
/**
 * Analytics Trends View - 趋势分析
 */

global $DB;

echo "<div class='analytics-content'>";
echo "<div class='content-header'>";
echo "<i class='fas fa-chart-line'></i> 趋势分析";
echo "</div>";

echo "<div class='content-body'>";

// Get historical scan data for trend analysis
$history_query = "SELECT id, scan_date, total_software, 
                  whitelist_count as total_approved, 
                  blacklist_count as total_blacklisted, 
                  unmanaged_count as total_unmanaged,
                  DATE(scan_date) as scan_day
                  FROM `glpi_plugin_softwaremanager_scanhistory` 
                  WHERE status = 'completed'
                  ORDER BY scan_date DESC 
                  LIMIT 30";
$history_result = $DB->query($history_query);

if ($history_result && $DB->numrows($history_result) > 0) {
    $historical_data = [];
    while ($row = $DB->fetchAssoc($history_result)) {
        $historical_data[] = $row;
    }
    
    // Reverse to show oldest first
    $historical_data = array_reverse($historical_data);
    
    if (count($historical_data) > 1) {
        echo "<div style='margin-bottom: 30px;'>";
        echo "<h4>合规趋势图表</h4>";
        
        // Simple trend chart using HTML/CSS
        echo "<div style='background: white; padding: 20px; border: 1px solid #ddd; border-radius: 4px;'>";
        echo "<div style='display: flex; justify-content: space-between; align-items: end; height: 200px; border-bottom: 2px solid #333; border-left: 2px solid #333; padding: 10px;'>";
        
        $max_software = max(array_column($historical_data, 'total_software'));
        $chart_width = 100 / count($historical_data);
        
        foreach ($historical_data as $index => $data) {
            $height_approved = $max_software > 0 ? ($data['total_approved'] / $max_software) * 180 : 0;
            $height_blacklisted = $max_software > 0 ? ($data['total_blacklisted'] / $max_software) * 180 : 0;
            $height_unmanaged = $max_software > 0 ? ($data['total_unmanaged'] / $max_software) * 180 : 0;
            
            echo "<div style='width: " . ($chart_width - 1) . "%; margin-right: 1%; position: relative;'>";
            
            // Stacked bars
            echo "<div style='position: absolute; bottom: 0; width: 100%; background: #5cb85c; height: {$height_approved}px;' title='合规: {$data['total_approved']}'></div>";
            echo "<div style='position: absolute; bottom: {$height_approved}px; width: 100%; background: #f0ad4e; height: {$height_unmanaged}px;' title='未登记: {$data['total_unmanaged']}'></div>";
            echo "<div style='position: absolute; bottom: " . ($height_approved + $height_unmanaged) . "px; width: 100%; background: #d9534f; height: {$height_blacklisted}px;' title='违规: {$data['total_blacklisted']}'></div>";
            
            // Date label
            echo "<div style='position: absolute; bottom: -30px; width: 100%; text-align: center; font-size: 10px; color: #666;'>";
            echo date('m/d', strtotime($data['scan_date']));
            echo "</div>";
            
            echo "</div>";
        }
        
        echo "</div>";
        
        // Legend
        echo "<div style='margin-top: 40px; display: flex; justify-content: center; gap: 20px;'>";
        echo "<div style='display: flex; align-items: center; gap: 5px;'><div style='width: 15px; height: 15px; background: #5cb85c;'></div> 合规软件</div>";
        echo "<div style='display: flex; align-items: center; gap: 5px;'><div style='width: 15px; height: 15px; background: #f0ad4e;'></div> 未登记软件</div>";
        echo "<div style='display: flex; align-items: center; gap: 5px;'><div style='width: 15px; height: 15px; background: #d9534f;'></div> 违规软件</div>";
        echo "</div>";
        
        echo "</div>";
        echo "</div>";
        
        // Trend analysis table
        echo "<h4>历史扫描记录</h4>";
        echo "<table class='tab_cadre_fixehov'>";
        echo "<tr class='noHover'>";
        echo "<th>扫描日期</th>";
        echo "<th>软件总数</th>";
        echo "<th>合规软件</th>";
        echo "<th>违规软件</th>";
        echo "<th>未登记软件</th>";
        echo "<th>合规率</th>";
        echo "<th>变化趋势</th>";
        echo "<th>操作</th>";
        echo "</tr>";
        
        $row_class_toggle = true;
        $prev_compliance_rate = null;
        
        foreach (array_reverse($historical_data) as $index => $data) {
            $row_class = $row_class_toggle ? 'tab_bg_1' : 'tab_bg_2';
            $row_class_toggle = !$row_class_toggle;
            
            $compliance_rate = $data['total_software'] > 0 ? round(($data['total_approved'] / $data['total_software']) * 100, 1) : 0;
            
            echo "<tr class='$row_class'>";
            echo "<td>" . Html::convDateTime($data['scan_date']) . "</td>";
            echo "<td>" . $data['total_software'] . "</td>";
            echo "<td><span style='color: #5cb85c; font-weight: bold;'>" . $data['total_approved'] . "</span></td>";
            echo "<td><span style='color: #d9534f; font-weight: bold;'>" . $data['total_blacklisted'] . "</span></td>";
            echo "<td><span style='color: #f0ad4e; font-weight: bold;'>" . $data['total_unmanaged'] . "</span></td>";
            
            $rate_color = $compliance_rate >= 80 ? '#5cb85c' : ($compliance_rate >= 60 ? '#f0ad4e' : '#d9534f');
            echo "<td><span style='color: $rate_color; font-weight: bold;'>{$compliance_rate}%</span></td>";
            
            // Trend indicator
            echo "<td>";
            if ($prev_compliance_rate !== null) {
                $diff = $compliance_rate - $prev_compliance_rate;
                if ($diff > 0) {
                    echo "<span style='color: #5cb85c;'>↗ +" . round($diff, 1) . "%</span>";
                } elseif ($diff < 0) {
                    echo "<span style='color: #d9534f;'>↘ " . round($diff, 1) . "%</span>";
                } else {
                    echo "<span style='color: #666;'>→ 持平</span>";
                }
            } else {
                echo "-";
            }
            echo "</td>";
            
            echo "<td><a href='?id={$data['id']}&view=overview' class='vsubmit'>查看详情</a></td>";
            echo "</tr>";
            
            $prev_compliance_rate = $compliance_rate;
        }
        echo "</table>";
        
    } else {
        echo "<div style='text-align: center; color: #666; padding: 40px;'>";
        echo "<i class='fas fa-info-circle' style='font-size: 48px; margin-bottom: 15px;'></i><br>";
        echo "<strong>需要更多数据</strong><br>";
        echo "<small>趋势分析需要至少2次扫描记录</small>";
        echo "</div>";
    }
} else {
    echo "<div style='text-align: center; color: #666; padding: 40px;'>";
    echo "<i class='fas fa-chart-line' style='font-size: 48px; margin-bottom: 15px;'></i><br>";
    echo "<strong>暂无历史数据</strong><br>";
    echo "<small>请先执行合规性扫描</small>";
    echo "</div>";
}

echo "</div>";
echo "</div>";
?>