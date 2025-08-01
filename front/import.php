<?php
/**
 * Software Manager Plugin for GLPI
 * Import Page
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
Html::header(__('Import Software Lists', 'softwaremanager'), $_SERVER['PHP_SELF'], 'admin', 'PluginSoftwaremanagerMenu');

// Display navigation
PluginSoftwaremanagerMenu::displayNavigationHeader('import');

echo "<div class='center'>";
echo "<h2>" . __('Import Software Lists', 'softwaremanager') . "</h2>";

echo "<div class='spaced'>";
echo "<table class='tab_cadre_fixe'>";
echo "<tr><th colspan='2'>" . __('Import from CSV file', 'softwaremanager') . "</th></tr>";

echo "<tr class='tab_bg_1'>";
echo "<td colspan='2'>";
echo "<p>" . __('Upload a CSV file to import software names into whitelist or blacklist.', 'softwaremanager') . "</p>";
echo "<p><strong>" . __('CSV Format:', 'softwaremanager') . "</strong></p>";
echo "<ul>";
echo "<li>" . __('First column: Software name (required)', 'softwaremanager') . "</li>";
echo "<li>" . __('Second column: Comment (optional)', 'softwaremanager') . "</li>";
echo "</ul>";
echo "<p><strong>" . __('Example:', 'softwaremanager') . "</strong></p>";
echo "<pre>Microsoft Office,Office suite\nAdobe Photoshop,Image editor\nNotepad++</pre>";
echo "</td>";
echo "</tr>";

echo "<tr class='tab_bg_1'>";
echo "<td width='30%'><label for='list_type'>" . __('Import to:', 'softwaremanager') . "</label></td>";
echo "<td>";
echo "<select name='list_type' id='list_type' required>";
echo "<option value=''>" . __('Select list type', 'softwaremanager') . "</option>";
echo "<option value='whitelist'>" . __('Whitelist', 'softwaremanager') . "</option>";
echo "<option value='blacklist'>" . __('Blacklist', 'softwaremanager') . "</option>";
echo "</select>";
echo "</td>";
echo "</tr>";

echo "<tr class='tab_bg_1'>";
echo "<td><label for='import_file'>" . __('CSV File:', 'softwaremanager') . "</label></td>";
echo "<td>";
echo "<input type='file' name='import_file' id='import_file' accept='.csv,.txt' required>";
echo "<br><small>" . __('Maximum file size: 5MB', 'softwaremanager') . "</small>";
echo "</td>";
echo "</tr>";

echo "<tr class='tab_bg_1'>";
echo "<td colspan='2' class='center'>";
echo "<button type='button' id='import_btn' class='submit'>" . __('Import', 'softwaremanager') . "</button>";
echo "</td>";
echo "</tr>";

echo "</table>";
echo "</div>";

// Results area
echo "<div id='import_results' style='display:none; margin-top: 20px;'>";
echo "<table class='tab_cadre_fixe'>";
echo "<tr><th>" . __('Import Results', 'softwaremanager') . "</th></tr>";
echo "<tr class='tab_bg_1'>";
echo "<td id='import_results_content'></td>";
echo "</tr>";
echo "</table>";
echo "</div>";

echo "</div>";

// JavaScript for import functionality
echo "<script type='text/javascript'>";
echo "
$(document).ready(function() {
    $('#import_btn').click(function() {
        var listType = $('#list_type').val();
        var fileInput = $('#import_file')[0];
        
        if (!listType) {
            alert('" . __('Please select a list type', 'softwaremanager') . "');
            return;
        }
        
        if (!fileInput.files.length) {
            alert('" . __('Please select a file to import', 'softwaremanager') . "');
            return;
        }
        
        var formData = new FormData();
        formData.append('import_file', fileInput.files[0]);
        formData.append('list_type', listType);
        formData.append('_glpi_csrf_token', '" . Session::getNewCSRFToken() . "');
        
        // Show loading
        $('#import_btn').prop('disabled', true).text('" . __('Importing...', 'softwaremanager') . "');
        
        $.ajax({
            url: '../ajax/import.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#import_results_content').html(formatImportResults(response));
                $('#import_results').show();
                
                if (response.success) {
                    // Reset form
                    $('#list_type').val('');
                    $('#import_file').val('');
                }
            },
            error: function(xhr) {
                var error = 'Import failed';
                try {
                    var response = JSON.parse(xhr.responseText);
                    error = response.error || error;
                } catch(e) {}
                
                $('#import_results_content').html('<div class=\"alert alert-danger\">' + error + '</div>');
                $('#import_results').show();
            },
            complete: function() {
                $('#import_btn').prop('disabled', false).text('" . __('Import', 'softwaremanager') . "');
            }
        });
    });
});

function formatImportResults(response) {
    var html = '';
    
    if (response.success) {
        html += '<div class=\"alert alert-success\">' + response.message + '</div>';
        
        if (response.errors && response.errors.length > 0) {
            html += '<h4>" . __('Errors:', 'softwaremanager') . "</h4>';
            html += '<ul>';
            response.errors.forEach(function(error) {
                html += '<li>' + error + '</li>';
            });
            html += '</ul>';
        }
    } else {
        html += '<div class=\"alert alert-danger\">' + response.error + '</div>';
    }
    
    return html;
}
";
echo "</script>";

Html::footer();
