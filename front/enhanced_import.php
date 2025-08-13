<?php
/**
 * Software Manager Plugin for GLPI
 * Enhanced Import Page with Preview and Field Mapping
 * 
 * @author  Abner Liu
 * @license GPL-2.0+
 */

include('../../../inc/includes.php');

// Check rights - allow access for authenticated users
if (!Session::getLoginUserID()) {
    Html::redirect($CFG_GLPI["root_doc"] . "/index.php");
    exit();
}

// Start page
Html::header(__('Enhanced Import Software Lists', 'softwaremanager'), $_SERVER['PHP_SELF'], 'admin', 'PluginSoftwaremanagerMenu');

// Display navigation
PluginSoftwaremanagerMenu::displayNavigationHeader('import');

echo "<div class='center'>";
echo "<h2>" . __('Enhanced Import Software Lists', 'softwaremanager') . "</h2>";

echo "<div class='spaced'>";
echo "<table class='tab_cadre_fixe'>";
echo "<tr><th colspan='2'>" . __('Import from CSV file with Preview', 'softwaremanager') . "</th></tr>";

echo "<tr class='tab_bg_1'>";
echo "<td colspan='2'>";
echo "<p>" . __('Upload a CSV file to preview and import software names into whitelist or blacklist with field mapping validation.', 'softwaremanager') . "</p>";
echo "<p><strong>" . __('CSV Format:', 'softwaremanager') . "</strong></p>";
echo "<ul>";
echo "<li>" . __('Column 1: Software name (required)', 'softwaremanager') . "</li>";
echo "<li>" . __('Column 2: Version (optional)', 'softwaremanager') . "</li>";
echo "<li>" . __('Column 3: Publisher (optional)', 'softwaremanager') . "</li>";
echo "<li>" . __('Column 4: Category (optional)', 'softwaremanager') . "</li>";
echo "<li>" . __('Column 5: Priority (optional, 0-10)', 'softwaremanager') . "</li>";
echo "<li>" . __('Column 6: Is active (optional, 0/1)', 'softwaremanager') . "</li>";
echo "<li>" . __('Column 7: Computers (optional, comma separated names)', 'softwaremanager') . "</li>";
echo "<li>" . __('Column 8: Users (optional, comma separated names)', 'softwaremanager') . "</li>";
echo "<li>" . __('Column 9: Groups (optional, comma separated names)', 'softwaremanager') . "</li>";
echo "<li>" . __('Column 10: Version rules (optional)', 'softwaremanager') . "</li>";
echo "<li>" . __('Column 11: Comment (optional)', 'softwaremanager') . "</li>";
echo "</ul>";
echo "</td>";
echo "</tr>";

echo "<tr class='tab_bg_1'>";
echo "<td width='30%'><label for='import_type'>" . __('Import to:', 'softwaremanager') . "</label></td>";
echo "<td>";
echo "<select name='import_type' id='import_type' required>";
echo "<option value=''>" . __('Select list type', 'softwaremanager') . "</option>";
echo "<option value='whitelist'>" . __('Whitelist', 'softwaremanager') . "</option>";
echo "<option value='blacklist'>" . __('Blacklist', 'softwaremanager') . "</option>";
echo "</select>";
echo "</td>";
echo "</tr>";

echo "<tr class='tab_bg_1'>";
echo "<td><label for='csv_file'>" . __('CSV File:', 'softwaremanager') . "</label></td>";
echo "<td>";
echo "<input type='file' name='csv_file' id='csv_file' accept='.csv,.txt' required>";
echo "<br><small>" . __('Maximum file size: 5MB', 'softwaremanager') . "</small>";
echo "</td>";
echo "</tr>";

echo "<tr class='tab_bg_1'>";
echo "<td colspan='2' class='center'>";
echo "<button type='button' id='preview_btn' class='submit'>" . __('Preview CSV', 'softwaremanager') . "</button>";
echo "</td>";
echo "</tr>";

echo "</table>";
echo "</div>";

// Preview area
echo "<div id='preview_area' style='display:none; margin-top: 20px;'>";
echo "<table class='tab_cadre_fixe'>";
echo "<tr><th>" . __('CSV Preview', 'softwaremanager') . "</th></tr>";
echo "<tr class='tab_bg_1'>";
echo "<td id='preview_content'></td>";
echo "</tr>";
echo "</table>";
echo "</div>";

// Field mapping area
echo "<div id='mapping_area' style='display:none; margin-top: 20px;'>";
echo "<table class='tab_cadre_fixe'>";
echo "<tr><th>" . __('Field Mapping Analysis', 'softwaremanager') . "</th></tr>";
echo "<tr class='tab_bg_1'>";
echo "<td id='mapping_content'></td>";
echo "</tr>";
echo "</table>";
echo "</div>";

// Import area
echo "<div id='import_area' style='display:none; margin-top: 20px;'>";
echo "<table class='tab_cadre_fixe'>";
echo "<tr><th>" . __('Import Options', 'softwaremanager') . "</th></tr>";
echo "<tr class='tab_bg_1'>";
echo "<td id='import_content'></td>";
echo "</tr>";
echo "</table>";
echo "</div>";

// Results area
echo "<div id='results_area' style='display:none; margin-top: 20px;'>";
echo "<table class='tab_cadre_fixe'>";
echo "<tr><th>" . __('Import Results', 'softwaremanager') . "</th></tr>";
echo "<tr class='tab_bg_1'>";
echo "<td id='results_content'></td>";
echo "</tr>";
echo "</table>";
echo "</div>";

echo "</div>";

// JavaScript for enhanced import functionality
echo "<script type='text/javascript'>";
echo "
$(document).ready(function() {
    let csvData = [];
    let importType = '';
    
    $('#preview_btn').click(function() {
        importType = $('#import_type').val();
        let fileInput = $('#csv_file')[0];
        
        if (!importType) {
            alert('" . __('Please select a list type', 'softwaremanager') . "');
            return;
        }
        
        if (!fileInput.files.length) {
            alert('" . __('Please select a file to import', 'softwaremanager') . "');
            return;
        }
        
        let formData = new FormData();
        formData.append('csv_file', fileInput.files[0]);
        formData.append('import_type', importType);
        formData.append('action', 'preview_csv');
        formData.append('_glpi_csrf_token', '" . Session::getNewCSRFToken() . "');
        
        // Show loading
        $('#preview_btn').prop('disabled', true).text('" . __('Previewing...', 'softwaremanager') . "');
        
        $.ajax({
            url: '../ajax/enhanced_import.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    csvData = response.headers ? [response.headers, ...response.preview_data.map(row => Object.values(row))] : [];
                    displayPreview(response);
                    displayMapping(response.mapping_analysis);
                    setupImport(response);
                } else {
                    alert(response.error);
                }
            },
            error: function(xhr) {
                let error = 'Preview failed';
                try {
                    let response = JSON.parse(xhr.responseText);
                    error = response.error || error;
                } catch(e) {}
                alert(error);
            },
            complete: function() {
                $('#preview_btn').prop('disabled', false).text('" . __('Preview CSV', 'softwaremanager') . "');
            }
        });
    });
    
    function displayPreview(response) {
        let html = '';
        
        html += '<div class=\"alert alert-info\">';
        html += '<strong>" . __('File Information', 'softwaremanager') . ":</strong><br>';
        html += '" . __('Total rows', 'softwaremanager') . ": ' + response.total_rows + '<br>';
        html += '" . __('Showing preview', 'softwaremanager') . ": ' + response.preview_data.length + ' " . __('rows', 'softwaremanager') . "';
        html += '</div>';
        
        html += '<div style=\"overflow-x: auto; max-height: 400px; overflow-y: auto;\">';
        html += '<table class=\"tab_cadre_fixehover\" style=\"min-width: 100%;\">';
        
        // Headers
        html += '<thead><tr>';
        response.headers.forEach(function(header) {
            html += '<th style=\"position: sticky; top: 0; background: #f0f0f0;\">' + header + '</th>';
        });
        html += '</tr></thead>';
        
        // Data rows
        html += '<tbody>';
        response.preview_data.forEach(function(row, index) {
            html += '<tr>';
            response.headers.forEach(function(header) {
                let value = row[header] || '';
                html += '<td>' + value + '</td>';
            });
            html += '</tr>';
        });
        html += '</tbody>';
        html += '</table>';
        html += '</div>';
        
        $('#preview_content').html(html);
        $('#preview_area').show();
    }
    
    function displayMapping(mappingAnalysis) {
        let html = '';
        
        // Field mapping
        html += '<h4>" . __('Field Mapping', 'softwaremanager') . "</h4>';
        html += '<table class=\"tab_cadre_fixehover\" style=\"width: 100%;\">';
        html += '<thead><tr>';
        html += '<th>" . __('Database Field', 'softwaremanager') . "</th>';
        html += '<th>" . __('CSV Column', 'softwaremanager') . "</th>';
        html += '<th>" . __('Required', 'softwaremanager') . "</th>';
        html += '<th>" . __('Data Type', 'softwaremanager') . "</th>';
        html += '</tr></thead>';
        html += '<tbody>';
        
        Object.entries(mappingAnalysis.field_mapping).forEach(function([field, info]) {
            html += '<tr>';
            html += '<td><code>' + field + '</code></td>';
            html += '<td>' + info.csv_column + '</td>';
            html += '<td>' + (info.required ? '" . __('Yes', 'softwaremanager') . "' : '" . __('No', 'softwaremanager') . "') + '</td>';
            html += '<td>' + info.type + '</td>';
            html += '</tr>';
        });
        html += '</tbody>';
        html += '</table>';
        
        // Name conversion analysis
        html += '<h4>" . __('Name to ID Mapping Analysis', 'softwaremanager') . "</h4>';
        
        // Computers mapping
        if (Object.keys(mappingAnalysis.name_conversion.computers).length > 0) {
            html += '<h5>" . __('Computers', 'softwaremanager') . "</h5>';
            html += '<div style=\"max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;\">';
            Object.entries(mappingAnalysis.name_conversion.computers).forEach(function([name, id]) {
                html += '<div style=\"margin-bottom: 5px;\">';
                html += '<strong>' + name + '</strong> → ';
                if (id) {
                    html += '<span style=\"color: green;\">ID: ' + id + '</span>';
                } else {
                    html += '<span style=\"color: red;\">" . __('Not found', 'softwaremanager') . "</span>';
                }
                html += '</div>';
            });
            html += '</div>';
        }
        
        // Users mapping
        if (Object.keys(mappingAnalysis.name_conversion.users).length > 0) {
            html += '<h5>" . __('Users', 'softwaremanager') . "</h5>';
            html += '<div style=\"max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;\">';
            Object.entries(mappingAnalysis.name_conversion.users).forEach(function([name, id]) {
                html += '<div style=\"margin-bottom: 5px;\">';
                html += '<strong>' + name + '</strong> → ';
                if (id) {
                    html += '<span style=\"color: green;\">ID: ' + id + '</span>';
                } else {
                    html += '<span style=\"color: red;\">" . __('Not found', 'softwaremanager') . "</span>';
                }
                html += '</div>';
            });
            html += '</div>';
        }
        
        // Groups mapping
        if (Object.keys(mappingAnalysis.name_conversion.groups).length > 0) {
            html += '<h5>" . __('Groups', 'softwaremanager') . "</h5>';
            html += '<div style=\"max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;\">';
            Object.entries(mappingAnalysis.name_conversion.groups).forEach(function([name, id]) {
                html += '<div style=\"margin-bottom: 5px;\">';
                html += '<strong>' + name + '</strong> → ';
                if (id) {
                    html += '<span style=\"color: green;\">ID: ' + id + '</span>';
                } else {
                    html += '<span style=\"color: red;\">" . __('Not found', 'softwaremanager') . "</span>';
                }
                html += '</div>';
            });
            html += '</div>';
        }
        
        // Statistics
        html += '<h4>" . __('Statistics', 'softwaremanager') . "</h4>';
        html += '<div style=\"background: #e9ecef; padding: 15px; border-radius: 5px;\">';
        html += '<div style=\"display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;\">';
        html += '<div><strong>" . __('Total rows', 'softwaremanager') . ":</strong> ' + mappingAnalysis.statistics.total_rows + '</div>';
        html += '<div><strong>" . __('Rows with computers', 'softwaremanager') . ":</strong> ' + mappingAnalysis.statistics.rows_with_computers + '</div>';
        html += '<div><strong>" . __('Rows with users', 'softwaremanager') . ":</strong> ' + mappingAnalysis.statistics.rows_with_users + '</div>';
        html += '<div><strong>" . __('Rows with groups', 'softwaremanager') . ":</strong> ' + mappingAnalysis.statistics.rows_with_groups + '</div>';
        html += '</div>';
        html += '</div>';
        
        $('#mapping_content').html(html);
        $('#mapping_area').show();
    }
    
    function setupImport(response) {
        let html = '';
        
        html += '<div class=\"alert alert-warning\">';
        html += '<strong>" . __('Ready to import', 'softwaremanager') . ":</strong><br>';
        html += '" . __('Total rows to import', 'softwaremanager') . ": ' + response.total_rows + '<br>';
        html += '" . __('Please review the preview and mapping above before proceeding', 'softwaremanager') . "';
        html += '</div>';
        
        html += '<div style=\"text-align: center; margin: 20px 0;\">';
        html += '<button type=\"button\" id=\"validate_btn\" class=\"submit\" style=\"margin-right: 10px;\">" . __('Validate Data', 'softwaremanager') . "</button>';
        html += '<button type=\"button\" id=\"import_btn\" class=\"submit\" style=\"margin-left: 10px;\">" . __('Import Now', 'softwaremanager') . "</button>';
        html += '</div>';
        
        // Add a placeholder for import results right below the buttons
        html += '<div id=\"import_results_inline\" style=\"display:none; margin-top: 15px;\"></div>';
        
        $('#import_content').html(html);
        $('#import_area').show();
        
        // Setup validation button
        $('#validate_btn').click(function() {
            validateMapping();
        });
        
        // Setup import button
        $('#import_btn').click(function() {
            performImport();
        });
    }
    
    function validateMapping() {
        let formData = new FormData();
        formData.append('csv_data', JSON.stringify(csvData));
        formData.append('import_type', importType);
        formData.append('action', 'validate_mapping');
        formData.append('_glpi_csrf_token', '" . Session::getNewCSRFToken() . "');
        
        $('#validate_btn').prop('disabled', true).text('" . __('Validating...', 'softwaremanager') . "');
        
        $.ajax({
            url: '../ajax/enhanced_import.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    displayValidationResults(response.validation_result);
                } else {
                    alert(response.error);
                }
            },
            error: function(xhr) {
                let error = 'Validation failed';
                try {
                    let response = JSON.parse(xhr.responseText);
                    error = response.error || error;
                } catch(e) {}
                alert(error);
            },
            complete: function() {
                $('#validate_btn').prop('disabled', false).text('" . __('Validate Data', 'softwaremanager') . "');
            }
        });
    }
    
    function displayValidationResults(validationResult) {
        let html = '';
        
        html += '<div class=\"alert alert-info\">';
        html += '<strong>" . __('Validation Results', 'softwaremanager') . ":</strong><br>';
        html += '" . __('Valid rows', 'softwaremanager') . ": ' + validationResult.valid_rows + '<br>';
        html += '" . __('Invalid rows', 'softwaremanager') . ": ' + validationResult.invalid_rows;
        html += '</div>';
        
        if (validationResult.invalid_rows > 0) {
            html += '<div style=\"max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff3cd;\">';
            html += '<h5>" . __('Issues found', 'softwaremanager') . ":</h5>';
            validationResult.mapping_details.forEach(function(detail) {
                if (detail.issues.length > 0) {
                    html += '<div style=\"margin-bottom: 10px; padding: 10px; background: #f8d7da; border-radius: 3px;\">';
                    html += '<strong>" . __('Row', 'softwaremanager') . " ' + detail.row_number + ':</strong><br>';
                    detail.issues.forEach(function(issue) {
                        html += '• ' + issue + '<br>';
                    });
                    html += '</div>';
                }
            });
            html += '</div>';
        }
        
        if (validationResult.invalid_rows === 0) {
            html += '<div class=\"alert alert-success\">" . __('All data is valid and ready for import!', 'softwaremanager') . "</div>';
        }
        
        // Replace import content with validation results
        $('#import_content').html(html);
    }
    
    function performImport() {
        let formData = new FormData();
        formData.append('csv_data', JSON.stringify(csvData));
        formData.append('import_type', importType);
        formData.append('action', 'import_data');
        formData.append('_glpi_csrf_token', '" . Session::getNewCSRFToken() . "');
        
        $('#import_btn').prop('disabled', true).text('" . __('Importing...', 'softwaremanager') . "');
        
        $.ajax({
            url: '../ajax/enhanced_import.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    displayImportResults(response);
                } else {
                    alert(response.error);
                }
            },
            error: function(xhr) {
                let error = 'Import failed';
                try {
                    let response = JSON.parse(xhr.responseText);
                    error = response.error || error;
                } catch(e) {}
                alert(error);
            },
            complete: function() {
                $('#import_btn').prop('disabled', false).text('" . __('Import Now', 'softwaremanager') . "');
            }
        });
    }
    
    function displayImportResults(response) {
        let html = '';
        
        html += '<div class=\"alert alert-success\">';
        html += '<strong>" . __('Import Completed', 'softwaremanager') . ":</strong><br>';
        html += response.message + '<br>';
        html += '" . __('Successfully imported', 'softwaremanager') . ": ' + response.success_count + ' " . __('items', 'softwaremanager') . "';
        html += '</div>';
        
        if (response.error_count > 0) {
            html += '<div style=\"max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f8d7da; margin-top: 10px;\">';
            html += '<h5>" . __('Errors encountered', 'softwaremanager') . ":</h5>';
            response.errors.forEach(function(error) {
                html += '<div style=\"margin-bottom: 5px;\">• ' + error + '</div>';
            });
            html += '</div>';
        }
        
        if (response.imported_items.length > 0) {
            html += '<div style=\"margin-top: 10px;\">';
            html += '<h5>" . __('Imported Items', 'softwaremanager') . ":</h5>';
            html += '<div style=\"max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #d4edda;\">';
            response.imported_items.forEach(function(item) {
                html += '<div style=\"margin-bottom: 5px;\">';
                html += '<strong>' + item.name + '</strong> (ID: ' + item.id + ') - " . __('Row', 'softwaremanager') . " ' + item.row_number;
                html += '</div>';
            });
            html += '</div>';
            html += '</div>';
        }
        
        // Display results in the inline area (below buttons) instead of bottom of page
        $('#import_results_inline').html(html).show();
        
        // 重新隐藏底部的主要结果区域
        $('#results_area').hide();
        let newImportHtml = '<div style=\"text-align: center; margin-top: 15px;\">';
        newImportHtml += '<button type=\"button\" id=\"new_import_btn\" class=\"submit\">' + __('Start New Import', 'softwaremanager') + '</button>';
        newImportHtml += '</div>';
        $('#import_results_inline').append(newImportHtml);
        // 设置新的导入按钮
        $('#new_import_btn').click(function() {
            // Reset everything and start over
            $('#import_type').val('');
            $('#csv_file').val('');
            $('#preview_area, #mapping_area, #import_area').hide();
            $('#import_results_inline').hide();
            csvData = [];
            importType = '';
        });
        
        // Don't reset form - let user see the results
        // Reset form
        // $('#import_type').val('');
        // $('#csv_file').val('');
        // $('#preview_area, #mapping_area').hide();
        
        // Hide the import area buttons since import is complete
        $('#validate_btn, #import_btn').hide();
    }
});
";
echo "</script>";

Html::footer();
?>