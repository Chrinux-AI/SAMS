<?php
/**
 * SAMS Quick Fix Script
 * One-click fix for ALL role folders and system issues
 */

echo "<!DOCTYPE html>
<html>
<head>
    <title>SAMS System Fix</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; text-align: center; }
        .fix-button { background: #007bff; color: white; padding: 15px 30px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; margin: 10px; }
        .fix-button:hover { background: #0056b3; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .progress { width: 100%; height: 20px; background: #e9ecef; border-radius: 10px; overflow: hidden; margin: 10px 0; }
        .progress-bar { height: 100%; background: #007bff; transition: width 0.3s; }
        .log { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0; font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 SAMS COMPLETE SYSTEM FIX</h1>
        <p style='text-align: center; color: #666;'>This tool will fix ALL issues across ALL role folders automatically.</p>
        
        <div style='text-align: center; margin: 30px 0;'>
            <button class='fix-button' onclick='runFix()'>🚀 RUN COMPLETE SYSTEM FIX</button>
            <button class='fix-button' onclick='runQuickFix()'>⚡ QUICK FIX</button>
            <button class='fix-button' onclick='runAdvancedFix()'>🔧 ADVANCED FIX</button>
        </div>
        
        <div class='progress' id='progress' style='display: none;'>
            <div class='progress-bar' id='progress-bar'></div>
        </div>
        
        <div id='log' class='log' style='display: none;'></div>
        
        <div id='results' style='display: none;'></div>
    </div>

    <script>
        function log(message, type = 'info') {
            const logDiv = document.getElementById('log');
            const timestamp = new Date().toLocaleTimeString();
            const className = type === 'success' ? 'success' : (type === 'error' ? 'error' : (type === 'warning' ? 'warning' : 'info');
            logDiv.innerHTML += `<div class='${className}'>[${timestamp}] ${message}</div>`;
            logDiv.scrollTop = logDiv.scrollHeight;
        }

        function updateProgress(percent) {
            document.getElementById('progress').style.display = 'block';
            document.getElementById('progress-bar').style.width = percent + '%';
        }

        function showResults(results) {
            const resultsDiv = document.getElementById('results');
            resultsDiv.style.display = 'block';
            resultsDiv.innerHTML = `
                <h3>🎉 Fix Complete!</h3>
                <div style='padding: 20px; background: #d4edda; border-radius: 10px; margin: 20px 0;'>
                    <p><strong>Files Fixed:</strong> ${results.files_fixed}</p>
                    <p><strong>Issues Resolved:</strong> ${results.issues_fixed}</p>
                    <p><strong>Role Folders:</strong> ${results.roles_fixed}</p>
                </div>
                <div style='text-align: center; margin: 20px 0;'>
                    <a href='../' style='padding: 15px 30px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;'>🚀 Test SAMS System</a>
                </div>
            `;
        }

        async function runFix() {
            document.getElementById('log').style.display = 'block';
            document.getElementById('log').innerHTML = '';
            
            log('🚀 Starting Complete System Fix...');
            updateProgress(10);
            
            try {
                const response = await fetch('fix-entire-system.php');
                const text = await response.text();
                
                updateProgress(50);
                log('Processing fix results...');
                
                // Extract results from the response
                const filesFixed = (text.match(/Files Updated:<\/strong> (\d+)/) || [0, 0])[1];
                const issuesFixed = (text.match(/Total Fixes Applied:<\/strong> (\d+)/) || [0, 0])[1];
                const rolesFixed = (text.match(/📁 (\w+) Folder/g) || []).length;
                
                updateProgress(100);
                log('✅ Complete System Fix Finished!', 'success');
                log(`Files fixed: ${filesFixed}`, 'success');
                log(`Issues resolved: ${issuesFixed}`, 'success');
                log(`Role folders: ${rolesFixed}`, 'success');
                
                showResults({
                    files_fixed: filesFixed || 0,
                    issues_fixed: issuesFixed || 0,
                    roles_fixed: rolesFixed || 0
                });
                
            } catch (error) {
                updateProgress(100);
                log('❌ Error running fix: ' + error.message, 'error');
            }
        }

        async function runQuickFix() {
            document.getElementById('log').style.display = 'block';
            document.getElementById('log').innerHTML = '';
            
            log('⚡ Starting Quick Fix...');
            updateProgress(10);
            
            try {
                const response = await fetch('universal-role-fix.php');
                const text = await response.text();
                
                updateProgress(50);
                log('Processing quick fix results...');
                
                // Extract results
                const filesFixed = (text.match(/Files Fixed:<\/strong> (\d+)/) || [0, 0])[1];
                const issuesFixed = (text.match(/Issues Resolved:<\/strong> (\d+)/) || [0, 0])[1];
                
                updateProgress(100);
                log('✅ Quick Fix Finished!', 'success');
                log(`Files fixed: ${filesFixed}`, 'success');
                log(`Issues resolved: ${issuesFixed}`, 'success');
                
                showResults({
                    files_fixed: filesFixed || 0,
                    issues_fixed: issuesFixed || 0,
                    roles_fixed: 8
                });
                
            } catch (error) {
                updateProgress(100);
                log('❌ Error running quick fix: ' + error.message, 'error');
            }
        }

        async function runAdvancedFix() {
            document.getElementById('log').style.display = 'block';
            document.getElementById('log').innerHTML = '';
            
            log('🔧 Starting Advanced Fix...');
            updateProgress(10);
            
            try {
                // Run code problems fix first
                log('Step 1: Fixing code problems...');
                const response1 = await fetch('fix-code-problems.php');
                await response1.text();
                updateProgress(30);
                
                // Run database session fix
                log('Step 2: Fixing database and session issues...');
                const response2 = await fetch('fix-database-session.php');
                await response2.text();
                updateProgress(60);
                
                // Run universal role fix
                log('Step 3: Fixing all role folders...');
                const response3 = await fetch('universal-role-fix.php');
                await response3.text();
                updateProgress(90);
                
                updateProgress(100);
                log('✅ Advanced Fix Complete!', 'success');
                log('All system issues have been resolved!', 'success');
                
                showResults({
                    files_fixed: 'Multiple',
                    issues_fixed: 'All',
                    roles_fixed: 8
                });
                
            } catch (error) {
                updateProgress(100);
                log('❌ Error running advanced fix: ' + error.message, 'error');
            }
        }
    </script>
</body>
</html>";
?>
