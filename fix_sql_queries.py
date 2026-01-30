#!/usr/bin/env python3
"""
Script to fix SQL queries in GLPI plugin files for GLPI 11.x compatibility
Replaces $DB->request(['SQL' => $sql]) with $DB->doQuery($sql)
"""

import re
import os

def fix_sql_queries(content):
    """
    Fix SQL queries in PHP code for GLPI 11.x compatibility

    Pattern 1: foreach ($DB->request(['FROM' => 'table', 'SQL' => $sql]) as $row) { ... }
    Pattern 2: foreach ($DB->request(['SQL' => $sql]) as $row) { ... }
    """
    result = content
    changes = 0

    # Pattern 1: Multi-line foreach with FROM and SQL
    # This is complex because we need to handle the foreach loop body
    pattern1 = r'''foreach\s*\(\s*\$DB->request\s*\(\s*\[\s*
        'FROM'\s*=>\s*([^,\]]+)\s*,\s*
        'SQL'\s*=>\s*(\$?\w+(?:\s*\.\s*[^\s]+)?)
        \s*\]\s*\)\s+as\s+\$(\w+)\s*\)\s*\{(.*?)\}'''

    # We need a more sophisticated approach - process line by line or in chunks
    lines = result.split('\n')
    i = 0
    output_lines = []

    while i < len(lines):
        line = lines[i]

        # Check if this line contains the start of a problematic pattern
        if "'SQL' =>" in line and "$DB->request" in line:
            # Look backwards to find the full $DB->request call
            start_line = i
            while start_line >= 0 and "$DB->request" not in lines[start_line]:
                start_line -= 1

            # Look for the closing ] and ) of $DB->request
            j = i
            while j < len(lines) and "]" not in lines[j]:
                j += 1

            # Look for the start of foreach ( as $row)
            k = j
            while k < len(lines) and "as $" not in lines[k]:
                k += 1

            # Find the closing brace
            if k < len(lines):
                brace_depth = 0
                l = k
                found_foreach_start = False
                # Find the opening {
                while l < len(lines):
                    if "{" in lines[l]:
                        found_foreach_start = True
                        break
                    l += 1

                if found_foreach_start:
                    # Extract the SQL variable name
                    sql_var = None
                    for m in range(start_line, i + 1):
                        match = re.search(r"'SQL'\s*=>\s*(\$?\w+(?:\s*\.\s*[^\s]+)?)", lines[m])
                        if match:
                            sql_var = match.group(1).strip()
                            break

                    if sql_var:
                        # Generate replacement code
                        new_lines = []
                        new_lines.append("    $result = $DB->doQuery(" + sql_var + ");")
                        new_lines.append("    if ($result) {")
                        new_lines.append("        while ($row = $result->fetch_assoc()) {")

                        output_lines.extend(new_lines)
                        changes += 1

                        # Skip the original lines up to and including the opening brace
                        i = l + 1
                        continue

        output_lines.append(line)
        i += 1

    return '\n'.join(output_lines), changes

def main():
    files_to_fix = [
        'inc/analytics_group.php',
        'inc/analytics_entity.php',
        'inc/analytics_user.php',
        'inc/analytics_software.php',
        'inc/analytics_trends.php',
        'front/analytics.php',
        'front/scanresult.php',
        'inc/softwareblacklist.class.php',
        'inc/compliance_runner.class.php',
        'inc/automailer.class.php',
        'ajax/check_tables.php',
        'ajax/check_glpi_tables.php',
        'ajax/setup_database.php',
        'ajax/export_direct.php',
        'front/ajax_get_rule_matches.php',
        'ajax/reinstall_guide.php',
    ]

    base_dir = r'C:\Users\Aberl.liu\Desktop\RDProject\GLPI\plugins\softwaremanager'

    total_changes = 0
    for file_path in files_to_fix:
        full_path = os.path.join(base_dir, file_path)
        if os.path.exists(full_path):
            with open(full_path, 'r', encoding='utf-8') as f:
                content = f.read()

            fixed_content, changes = fix_sql_queries(content)

            if changes > 0:
                with open(full_path, 'w', encoding='utf-8') as f:
                    f.write(fixed_content)
                print(f"Fixed {changes} queries in {file_path}")
                total_changes += changes
            else:
                print(f"No changes needed in {file_path}")
        else:
            print(f"File not found: {file_path}")

    print(f"\nTotal fixes: {total_changes}")

if __name__ == '__main__':
    main()
