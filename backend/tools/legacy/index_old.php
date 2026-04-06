<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/database.php';

if (is_logged_in()) {
    $role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? 'student';
    switch ($role) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'teacher':
            header('Location: teacher/dashboard.php');
            break;
        case 'student':
            header('Location: student/dashboard.php');
            break;
        case 'parent':
            header('Location: parent/dashboard.php');
            break;
        default:
            header('Location: login.php');
            break;
    }
    exit;
}

// Pull real statistics from the database
try {
    $stat_students = db()->fetchOne("SELECT COUNT(*) as cnt FROM students WHERE is_active = 1")['cnt'] ?? 0;
    $stat_teachers = db()->fetchOne("SELECT COUNT(*) as cnt FROM users WHERE role = 'teacher' AND is_active = 1")['cnt'] ?? 0;
    $stat_classes = db()->fetchOne("SELECT COUNT(*) as cnt FROM classes WHERE is_active = 1")['cnt'] ?? 0;
    $stat_parents = db()->fetchOne("SELECT COUNT(*) as cnt FROM users WHERE role = 'parent' AND is_active = 1")['cnt'] ?? 0;
    $stat_today = db()->fetchOne("SELECT COUNT(*) as cnt FROM attendance WHERE date = CURDATE() AND status = 'present'")['cnt'] ?? 0;
    $stat_rate = 0;
    $total_today = db()->fetchOne("SELECT COUNT(*) as cnt FROM attendance WHERE date = CURDATE()")['cnt'] ?? 0;
    if ($total_today > 0) $stat_rate = round(($stat_today / $total_today) * 100);
} catch (Exception $e) {
    $stat_students = 0;
    $stat_teachers = 0;
    $stat_classes = 0;
    $stat_parents = 0;
    $stat_today = 0;
    $stat_rate = 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head><?php include_once "includes/favicon-loader.php"; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> | Smart School Attendance Management</title>
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#4F46E5">
    <meta name="description" content="Modern school attendance management system with biometric check-in, real-time analytics, parent communication, and role-based dashboards.">
    <link rel="apple-touch-icon" href="/attendance/assets/images/icons/icon-192x192.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/professional-ui.css">
    <script src="assets/js/theme-loader.js"></script>
    <style>
        /* ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ RESET & BASE ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            overflow-x: hidden;
        }

        /* ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ ANIMATED BACKGROUND ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ */
        .lp {
            min-height: 100vh;
            position: relative;
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .lp::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background:
                radial-gradient(ellipse 900px 600px at 15% 0%, color-mix(in srgb, var(--primary) 14%, transparent), transparent 70%),
                radial-gradient(ellipse 700px 500px at 85% 5%, color-mix(in srgb, var(--primary-light) 12%, transparent), transparent 70%),
                radial-gradient(ellipse 500px 400px at 50% 80%, color-mix(in srgb, var(--primary) 8%, transparent), transparent 70%);
        }

        .lp>* {
            position: relative;
            z-index: 1;
        }

        /* ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ NAVBAR ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ */
        .lp-nav {
            position: sticky;
            top: 0;
            z-index: 1200;
            backdrop-filter: blur(16px) saturate(1.4);
            -webkit-backdrop-filter: blur(16px) saturate(1.4);
            border-bottom: 1px solid color-mix(in srgb, var(--border-color) 60%, transparent);
            background: color-mix(in srgb, var(--bg-white) 80%, transparent);
            transition: box-shadow .3s, background .3s;
        }

        .lp-nav.scrolled {
            box-shadow: 0 4px 30px rgba(0, 0, 0, .06);
            background: color-mix(in srgb, var(--bg-white) 92%, transparent);
        }

        .lp-nav-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .lp-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: inherit;
        }

        .lp-brand-icon {
            width: 42px;
            height: 42px;
            border-radius: 13px;
            display: grid;
            place-items: center;
            font-size: 1.1rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: #fff;
            box-shadow: 0 8px 24px color-mix(in srgb, var(--primary) 30%, transparent);
            transition: transform .3s;
        }

        .lp-brand:hover .lp-brand-icon {
            transform: scale(1.05) rotate(-4deg);
        }

        .lp-brand-name {
            font-family: Manrope, Inter, sans-serif;
            font-weight: 800;
            font-size: 1.15rem;
            letter-spacing: -0.02em;
        }

        .lp-nav-links {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .lp-nav-links a {
            text-decoration: none;
            font-size: .84rem;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 8px;
            color: var(--text-secondary);
            transition: .2s;
        }

        .lp-nav-links a:hover {
            color: var(--primary);
            background: var(--primary-50);
        }

        .lp-nav-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .lp-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            border-radius: 10px;
            padding: 10px 18px;
            font-size: .84rem;
            font-weight: 700;
            border: 1.5px solid var(--border-color);
            transition: all .25s ease;
            cursor: pointer;
            font-family: Inter, sans-serif;
        }

        .lp-btn.ghost {
            color: var(--text-secondary);
            background: transparent;
        }

        .lp-btn.ghost:hover {
            color: var(--primary);
            border-color: var(--primary);
            background: var(--primary-50);
        }

        .lp-btn.solid {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
            box-shadow: 0 4px 14px color-mix(in srgb, var(--primary) 35%, transparent);
        }

        .lp-btn.solid:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px color-mix(in srgb, var(--primary) 40%, transparent);
        }

        .lp-btn.lg {
            padding: 14px 28px;
            font-size: .92rem;
            border-radius: 12px;
        }

        .lp-mobile-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.3rem;
            color: var(--text-primary);
            cursor: pointer;
            padding: 6px;
        }

        /* ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ HERO ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ */
        .lp-hero {
            max-width: 1200px;
            margin: 0 auto;
            padding: 72px 24px 48px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 48px;
            align-items: center;
        }

        .lp-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1.5px solid color-mix(in srgb, var(--primary) 30%, var(--border-color));
            background: var(--primary-50);
            color: var(--primary-dark);
            border-radius: 999px;
            padding: 7px 16px;
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            margin-bottom: 20px;
            animation: fadeSlideDown .8s ease;
        }

        .lp-hero-badge .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--primary);
            animation: pulseDot 2s ease infinite;
        }

        @keyframes pulseDot {

            0%,
            100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: .4;
                transform: scale(.7);
            }
        }

        .lp-hero h1 {
            font-family: Manrope, Inter, sans-serif;
            font-size: clamp(2.2rem, 4.5vw, 3.6rem);
            line-height: 1.08;
            letter-spacing: -0.035em;
            margin: 0 0 20px;
            font-weight: 800;
            animation: fadeSlideDown .8s ease .1s both;
        }

        .lp-hero h1 .grad {
            background: linear-gradient(135deg, var(--primary), var(--primary-light), #22c55e);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            background-size: 200% 200%;
            animation: gradientShift 4s ease infinite;
        }

        @keyframes gradientShift {

            0%,
            100% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }
        }

        .lp-hero-desc {
            margin: 0 0 28px;
            color: var(--text-secondary);
            font-size: 1.05rem;
            line-height: 1.75;
            max-width: 56ch;
            animation: fadeSlideDown .8s ease .2s both;
        }

        .lp-hero-cta {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            animation: fadeSlideDown .8s ease .3s both;
        }

        @keyframes fadeSlideDown {
            from {
                opacity: 0;
                transform: translateY(16px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Hero Visual Card */
        .lp-hero-visual {
            animation: fadeSlideDown .8s ease .2s both;
            perspective: 1200px;
        }

        .lp-dashboard-preview {
            border: 1.5px solid var(--border-color);
            background: var(--bg-white);
            border-radius: 20px;
            padding: 0;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .08), 0 0 0 1px color-mix(in srgb, var(--primary) 5%, transparent);
            transition: transform .5s ease;
            transform: rotateY(-2deg) rotateX(1deg);
        }

        .lp-dashboard-preview:hover {
            transform: rotateY(0) rotateX(0);
        }

        .lp-dash-header {
            padding: 14px 18px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: #fff;
        }

        .lp-dash-header-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .lp-dash-dots {
            display: flex;
            gap: 5px;
        }

        .lp-dash-dots span {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .3);
        }

        .lp-dash-dots span:first-child {
            background: #f87171;
        }

        .lp-dash-dots span:nth-child(2) {
            background: #fbbf24;
        }

        .lp-dash-dots span:nth-child(3) {
            background: #34d399;
        }

        .lp-dash-body {
            padding: 18px;
            display: grid;
            gap: 14px;
        }

        .lp-dash-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .lp-mini-card {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 12px;
            background: var(--bg-primary);
            text-align: center;
            transition: transform .3s, box-shadow .3s;
        }

        .lp-mini-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, .06);
        }

        .lp-mini-card .mc-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            margin: 0 auto 8px;
            display: grid;
            place-items: center;
            font-size: .85rem;
        }

        .lp-mini-card .mc-val {
            font-family: Manrope, sans-serif;
            font-size: 1.3rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .lp-mini-card .mc-lbl {
            font-size: .72rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .mc-green {
            background: #dcfce7;
            color: #16a34a;
        }

        .mc-amber {
            background: #fef3c7;
            color: #d97706;
        }

        .mc-red {
            background: #fee2e2;
            color: #dc2626;
        }

        .lp-mini-chart {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 14px;
            background: var(--bg-primary);
        }

        .lp-chart-bars {
            display: flex;
            align-items: flex-end;
            gap: 6px;
            height: 60px;
            padding: 0 4px;
        }

        .lp-chart-bar {
            flex: 1;
            border-radius: 4px 4px 0 0;
            background: var(--primary);
            opacity: .7;
            animation: barGrow 1.2s ease forwards;
            transform-origin: bottom;
        }

        @keyframes barGrow {
            from {
                transform: scaleY(0);
            }

            to {
                transform: scaleY(1);
            }
        }

        /* ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ TRUST BAR ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ */
        .lp-trust {
            max-width: 1200px;
            margin: 0 auto;
            padding: 12px 24px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 32px;
            flex-wrap: wrap;
            opacity: .6;
            font-size: .78rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        .lp-trust i {
            color: var(--primary);
            margin-right: 6px;
        }

        /* ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ METRICS ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ */
        .lp-metrics {
            max-width: 1200px;
            margin: 40px auto 0;
            padding: 0 24px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }

        .lp-metric {
            border: 1.5px solid var(--border-color);
            background: var(--bg-white);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            transition: transform .3s, box-shadow .3s;
        }

        .lp-metric:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, .06);
        }

        .lp-metric-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            margin: 0 auto 12px;
            display: grid;
            place-items: center;
            font-size: 1.15rem;
            background: var(--primary-50);
            color: var(--primary);
        }

        .lp-metric-val {
            font-family: Manrope, sans-serif;
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: var(--text-primary);
        }

        .lp-metric-lbl {
            color: var(--text-secondary);
            font-size: .84rem;
            margin-top: 2px;
        }

        /* ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ SECTION SHARED ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ */
        .lp-section {
            max-width: 1200px;
            margin: 0 auto;
            padding: 80px 24px 0;
        }

        .lp-section-head {
            text-align: center;
            max-width: 640px;
            margin: 0 auto 40px;
        }

        .lp-section-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: var(--primary);
            background: var(--primary-50);
            padding: 5px 14px;
            border-radius: 999px;
            margin-bottom: 14px;
        }

        .lp-section-head h2 {
            font-family: Manrope, Inter, sans-serif;
            font-size: clamp(1.5rem, 3vw, 2.2rem);
            letter-spacing: -0.025em;
            margin: 0 0 12px;
            font-weight: 800;
        }

        .lp-section-head p {
            color: var(--text-secondary);
            font-size: 1rem;
            line-height: 1.7;
            margin: 0;
        }

        /* ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ FEATURES ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ */
        .lp-features {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        .lp-feat {
            border: 1.5px solid var(--border-color);
            border-radius: 16px;
            background: var(--bg-white);
            padding: 24px;
            transition: all .3s ease;
            position: relative;
            overflow: hidden;
        }

        .lp-feat::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform .4s ease;
        }

        .lp-feat:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, .06);
            border-color: color-mix(in srgb, var(--primary) 30%, var(--border-color));
        }

        .lp-feat:hover::before {
            transform: scaleX(1);
        }

        .lp-feat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            font-size: 1.1rem;
            margin-bottom: 14px;
            background: var(--primary-50);
            color: var(--primary);
        }

        .lp-feat h3 {
            font-size: 1.02rem;
            margin: 0 0 8px;
            font-weight: 700;
        }

        .lp-feat p {
            font-size: .88rem;
            color: var(--text-secondary);
            line-height: 1.7;
            margin: 0;
        }

        /* ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ HOW IT WORKS ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ */
        .lp-steps {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            position: relative;
        }

        .lp-steps::before {
            content: '';
            position: absolute;
            top: 36px;
            left: 60px;
            right: 60px;
            height: 2px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light), var(--primary));
            opacity: .2;
        }

        .lp-step {
            text-align: center;
            position: relative;
        }

        .lp-step-num {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            margin: 0 auto 16px;
            display: grid;
            place-items: center;
            font-family: Manrope, sans-serif;
            font-weight: 800;
            font-size: 1.1rem;
            border: 2.5px solid var(--primary);
            color: var(--primary);
            background: var(--bg-white);
            box-shadow: 0 4px 16px color-mix(in srgb, var(--primary) 15%, transparent);
            transition: all .3s;
        }

        .lp-step:hover .lp-step-num {
            background: var(--primary);
            color: #fff;
            transform: scale(1.1);
        }

        .lp-step h4 {
            font-size: .94rem;
            margin: 0 0 6px;
        }

        .lp-step p {
            font-size: .82rem;
            color: var(--text-secondary);
            line-height: 1.6;
            margin: 0;
        }

        /* ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ ROLES ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ */
        .lp-roles {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }

        .lp-role {
            border: 1.5px solid var(--border-color);
            border-radius: 16px;
            background: var(--bg-white);
            padding: 24px;
            text-align: center;
            transition: all .3s;
        }

        .lp-role:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, .06);
            border-color: color-mix(in srgb, var(--primary) 30%, var(--border-color));
        }

        .lp-role-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            margin: 0 auto 14px;
            display: grid;
            place-items: center;
            font-size: 1.3rem;
        }

        .lp-role h3 {
            font-size: 1rem;
            margin: 0 0 8px;
            font-weight: 700;
        }

        .lp-role p {
            font-size: .84rem;
            color: var(--text-secondary);
            line-height: 1.6;
            margin: 0 0 14px;
        }

        .lp-role ul {
            text-align: left;
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 6px;
        }

        .lp-role ul li {
            font-size: .82rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .lp-role ul li::before {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            font-size: .65rem;
            color: var(--primary);
        }

        .r-blue {
            background: linear-gradient(135deg, #dbeafe, #eff6ff);
            color: #2563eb;
        }

        .r-green {
            background: linear-gradient(135deg, #dcfce7, #f0fdf4);
            color: #16a34a;
        }

        .r-purple {
            background: linear-gradient(135deg, #f3e8ff, #faf5ff);
            color: #9333ea;
        }

        .r-amber {
            background: linear-gradient(135deg, #fef3c7, #fffbeb);
            color: #d97706;
        }

        /* ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ TESTIMONIALS ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ */
        .lp-testimonials {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        .lp-testimonial {
            border: 1.5px solid var(--border-color);
            border-radius: 16px;
            background: var(--bg-white);
            padding: 24px;
            transition: all .3s;
        }

        .lp-testimonial:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, .05);
        }

        .lp-testimonial-stars {
            color: #f59e0b;
            font-size: .85rem;
            margin-bottom: 12px;
        }

        .lp-testimonial blockquote {
            margin: 0 0 16px;
            font-size: .9rem;
            line-height: 1.7;
            color: var(--text-secondary);
            font-style: italic;
        }

        .lp-testimonial-author {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .lp-testimonial-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-weight: 700;
            font-size: .82rem;
            color: #fff;
        }

        .lp-testimonial-info strong {
            display: block;
            font-size: .84rem;
        }

        .lp-testimonial-info span {
            font-size: .76rem;
            color: var(--text-muted);
        }

        /* ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ FAQ ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ */
        .lp-faq {
            max-width: 720px;
            margin: 0 auto;
            display: grid;
            gap: 10px;
        }

        .lp-faq-item {
            border: 1.5px solid var(--border-color);
            border-radius: 14px;
            background: var(--bg-white);
            overflow: hidden;
            transition: border-color .3s;
        }

        .lp-faq-item.active {
            border-color: color-mix(in srgb, var(--primary) 40%, var(--border-color));
        }

        .lp-faq-q {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 20px;
            cursor: pointer;
            font-weight: 600;
            font-size: .92rem;
            background: none;
            border: none;
            width: 100%;
            text-align: left;
            color: var(--text-primary);
            font-family: Inter, sans-serif;
            transition: color .2s;
        }

        .lp-faq-q:hover {
            color: var(--primary);
        }

        .lp-faq-q i {
            transition: transform .3s;
            color: var(--text-muted);
            font-size: .8rem;
        }

        .lp-faq-item.active .lp-faq-q i {
            transform: rotate(180deg);
            color: var(--primary);
        }

        .lp-faq-a {
            max-height: 0;
            overflow: hidden;
            transition: max-height .35s ease, padding .35s ease;
        }

        .lp-faq-item.active .lp-faq-a {
            max-height: 200px;
        }

        .lp-faq-a p {
            margin: 0;
            padding: 0 20px 16px;
            font-size: .88rem;
            color: var(--text-secondary);
            line-height: 1.7;
        }

        /* ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ CTA ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ */
        .lp-cta {
            max-width: 1200px;
            margin: 80px auto 0;
            padding: 0 24px;
        }

        .lp-cta-box {
            border-radius: 20px;
            padding: 48px;
            text-align: center;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            position: relative;
            overflow: hidden;
        }

        .lp-cta-box::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 20% 50%, rgba(255, 255, 255, .1), transparent 50%),
                radial-gradient(circle at 80% 50%, rgba(255, 255, 255, .05), transparent 50%);
        }

        .lp-cta-box>* {
            position: relative;
            z-index: 1;
        }

        .lp-cta-box h2 {
            font-family: Manrope, sans-serif;
            font-size: clamp(1.4rem, 3vw, 2rem);
            margin: 0 0 10px;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .lp-cta-box p {
            margin: 0 0 24px;
            opacity: .85;
            font-size: 1rem;
        }

        .lp-cta-actions {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .lp-btn.white {
            background: #fff;
            color: var(--primary-dark);
            border-color: #fff;
            font-weight: 700;
        }

        .lp-btn.white:hover {
            background: #f8f8f8;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, .15);
        }

        .lp-btn.outline-w {
            background: transparent;
            color: #fff;
            border-color: rgba(255, 255, 255, .4);
        }

        .lp-btn.outline-w:hover {
            border-color: #fff;
            background: rgba(255, 255, 255, .1);
        }

        /* ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ FOOTER ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ */
        .lp-footer {
            max-width: 1200px;
            margin: 0 auto;
            padding: 48px 24px 0;
        }

        .lp-footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 32px;
            padding-bottom: 32px;
            border-bottom: 1px solid var(--border-color);
        }

        .lp-footer-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            text-decoration: none;
            color: inherit;
        }

        .lp-footer-brand-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: #fff;
            font-size: .9rem;
        }

        .lp-footer-brand-name {
            font-family: Manrope, sans-serif;
            font-weight: 800;
            font-size: 1rem;
        }

        .lp-footer-desc {
            font-size: .84rem;
            color: var(--text-secondary);
            line-height: 1.7;
            margin: 0 0 16px;
            max-width: 40ch;
        }

        .lp-footer-social {
            display: flex;
            gap: 8px;
        }

        .lp-footer-social a {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            background: var(--primary-50);
            color: var(--primary);
            font-size: .85rem;
            text-decoration: none;
            transition: all .2s;
        }

        .lp-footer-social a:hover {
            background: var(--primary);
            color: #fff;
        }

        .lp-footer h4 {
            font-size: .82rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--text-muted);
            margin: 0 0 14px;
            font-weight: 700;
        }

        .lp-footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 8px;
        }

        .lp-footer-links a {
            text-decoration: none;
            font-size: .86rem;
            color: var(--text-secondary);
            transition: color .2s;
        }

        .lp-footer-links a:hover {
            color: var(--primary);
        }

        .lp-footer-bottom {
            padding: 20px 0;
            text-align: center;
            color: var(--text-muted);
            font-size: .8rem;
        }

        /* ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ COMPARISON ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ */
        .lp-compare {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 24px;
            align-items: stretch;
        }

        .lp-compare-col {
            border-radius: 16px;
            padding: 28px;
        }

        .lp-compare-old {
            background: color-mix(in srgb, #ef4444 8%, var(--bg-white));
            border: 1.5px solid color-mix(in srgb, #ef4444 20%, var(--border-color));
        }

        .lp-compare-new {
            background: color-mix(in srgb, #22c55e 8%, var(--bg-white));
            border: 1.5px solid color-mix(in srgb, #22c55e 20%, var(--border-color));
        }

        .lp-compare-header {
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .lp-compare-old .lp-compare-header {
            color: #dc2626;
        }

        .lp-compare-new .lp-compare-header {
            color: #16a34a;
        }

        .lp-compare-col ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 12px;
        }

        .lp-compare-col li {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: .9rem;
            color: var(--text-secondary);
        }

        .lp-compare-old li i {
            color: #ef4444;
            font-size: .75rem;
            width: 16px;
            text-align: center;
        }

        .lp-compare-new li i {
            color: #22c55e;
            font-size: .75rem;
            width: 16px;
            text-align: center;
        }

        .lp-compare-divider {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .lp-compare-vs {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: var(--bg-white);
            border: 2px solid var(--border-color);
            font-weight: 800;
            font-size: .85rem;
            color: var(--text-muted);
            font-family: Manrope, sans-serif;
            box-shadow: 0 4px 16px rgba(0, 0, 0, .06);
        }

        /* ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ TECHNOLOGY ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ */
        .lp-tech-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        .lp-tech-card {
            border: 1.5px solid var(--border-color);
            border-radius: 14px;
            background: var(--bg-white);
            padding: 22px;
            display: flex;
            align-items: flex-start;
            gap: 16px;
            transition: all .3s;
        }

        .lp-tech-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, .05);
            border-color: color-mix(in srgb, var(--primary) 25%, var(--border-color));
        }

        .lp-tech-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            font-size: 1rem;
            background: var(--primary-50);
            color: var(--primary);
        }

        .lp-tech-card h4 {
            font-size: .92rem;
            margin: 0 0 4px;
            font-weight: 700;
        }

        .lp-tech-card p {
            font-size: .82rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ AURORA BACKGROUND ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ */
        .lp-aurora {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            overflow: hidden;
        }

        .lp-aurora-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            opacity: .15;
            animation: auroraFloat linear infinite;
        }

        .lp-aurora-orb:nth-child(1) {
            width: 600px; height: 600px;
            background: var(--primary);
            top: -15%; left: -10%;
            animation-duration: 22s;
        }

        .lp-aurora-orb:nth-child(2) {
            width: 500px; height: 500px;
            background: var(--primary-light);
            top: 50%; right: -10%;
            animation-duration: 18s;
            animation-delay: -6s;
        }

        .lp-aurora-orb:nth-child(3) {
            width: 400px; height: 400px;
            background: #22c55e;
            bottom: -10%; left: 30%;
            animation-duration: 25s;
            animation-delay: -12s;
        }

        @keyframes auroraFloat {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(60px, -40px) scale(1.1); }
            50% { transform: translate(-30px, 60px) scale(.9); }
            75% { transform: translate(40px, 30px) scale(1.05); }
        }

        /* ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ MOUSE GLOW ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ */
        .lp-glow {
            position: fixed;
            width: 500px; height: 500px;
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
            background: radial-gradient(circle, color-mix(in srgb, var(--primary) 10%, transparent), transparent 70%);
            transform: translate(-50%, -50%);
            transition: left .3s ease-out, top .3s ease-out;
            will-change: left, top;
        }

        /* ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ INTEGRATIONS MARQUEE ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ */
        .lp-integrations {
            max-width: 1200px;
            margin: 0 auto;
            padding: 60px 24px 0;
            text-align: center;
        }

        .lp-integrations-label {
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: var(--text-muted);
            margin-bottom: 24px;
        }

        .lp-marquee {
            overflow: hidden;
            mask-image: linear-gradient(90deg, transparent, #000 15%, #000 85%, transparent);
            -webkit-mask-image: linear-gradient(90deg, transparent, #000 15%, #000 85%, transparent);
        }

        .lp-marquee-track {
            display: flex;
            gap: 40px;
            animation: marqueeScroll 30s linear infinite;
            width: max-content;
        }

        .lp-marquee-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            border: 1.5px solid var(--border-color);
            border-radius: 12px;
            background: var(--bg-white);
            white-space: nowrap;
            font-size: .84rem;
            font-weight: 600;
            color: var(--text-secondary);
            flex-shrink: 0;
            transition: all .3s;
        }

        .lp-marquee-item:hover {
            border-color: color-mix(in srgb, var(--primary) 30%, var(--border-color));
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,.05);
        }

        .lp-marquee-item i {
            font-size: 1.1rem;
            color: var(--primary);
        }

        @keyframes marqueeScroll {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }

        /* ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ DASHBOARD 3D TILT ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ */
        .lp-dashboard-preview {
            transition: transform .15s ease-out, box-shadow .3s;
        }

        .lp-dashboard-preview.tilting {
            transform: perspective(1200px) rotateX(var(--rx, 0deg)) rotateY(var(--ry, 0deg)) !important;
            box-shadow: 0 30px 80px rgba(0,0,0,.12), 0 0 0 1px color-mix(in srgb, var(--primary) 5%, transparent);
        }

        .lp-dash-glow {
            position: absolute;
            inset: 0;
            border-radius: inherit;
            pointer-events: none;
            z-index: 10;
            opacity: 0;
            transition: opacity .3s;
            background: radial-gradient(circle at var(--gx, 50%) var(--gy, 50%), rgba(255,255,255,.15), transparent 60%);
        }

        .lp-dashboard-preview:hover .lp-dash-glow {
            opacity: 1;
        }

        /* ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ LIVE INDICATOR PULSE ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ */
        .lp-live-dot {
            display: inline-block;
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #22c55e;
            margin-right: 6px;
            animation: livePulse 2s ease infinite;
            vertical-align: middle;
        }

        @keyframes livePulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(34,197,94,.5); }
            50% { box-shadow: 0 0 0 6px rgba(34,197,94,0); }
        }

        /* ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ TYPEWRITER CURSOR ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ */
        .typed-cursor {
            display: inline-block;
            font-weight: 300;
            color: var(--primary);
            animation: blink 1s step-end infinite;
        }

        @keyframes blink {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0;
            }
        }

        /* ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ FLOATING PARTICLES ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ */
        .lp-particles {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            overflow: hidden;
        }

        .lp-particle {
            position: absolute;
            border-radius: 50%;
            background: color-mix(in srgb, var(--primary) 15%, transparent);
            animation: floatParticle linear infinite;
        }

        @keyframes floatParticle {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }

            10% {
                opacity: 1;
            }

            90% {
                opacity: 1;
            }

            100% {
                transform: translateY(-10vh) rotate(360deg);
                opacity: 0;
            }
        }

        /* ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ SCROLL ANIMATIONS ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ */
        .sa {
            opacity: 0;
            transform: translateY(24px);
            transition: opacity .6s ease, transform .6s ease;
        }

        .sa.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .sa-d1 {
            transition-delay: .1s;
        }

        .sa-d2 {
            transition-delay: .2s;
        }

        .sa-d3 {
            transition-delay: .3s;
        }

        .sa-d4 {
            transition-delay: .4s;
        }

        .sa-d5 {
            transition-delay: .5s;
        }

        /* ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ RESPONSIVE ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ */
        @media (max-width: 1024px) {
            .lp-hero {
                grid-template-columns: 1fr;
                padding: 48px 24px 32px;
            }

            .lp-hero-visual {
                max-width: 520px;
                margin: 0 auto;
            }

            .lp-dashboard-preview {
                transform: none;
            }

            .lp-metrics {
                grid-template-columns: repeat(2, 1fr);
            }

            .lp-features {
                grid-template-columns: repeat(2, 1fr);
            }

            .lp-steps {
                grid-template-columns: repeat(2, 1fr);
            }

            .lp-steps::before {
                display: none;
            }

            .lp-roles {
                grid-template-columns: repeat(2, 1fr);
            }

            .lp-testimonials {
                grid-template-columns: repeat(2, 1fr);
            }

            .lp-footer-grid {
                grid-template-columns: 1fr 1fr;
            }

            .lp-tech-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .lp-compare {
                grid-template-columns: 1fr;
            }

            .lp-compare-divider {
                padding: 0;
            }
        }

        @media (max-width: 640px) {
            .lp-hero {
                padding: 32px 16px 24px;
            }

            .lp-metrics,
            .lp-features,
            .lp-roles,
            .lp-testimonials {
                grid-template-columns: 1fr;
            }

            .lp-steps {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .lp-nav-links {
                display: none;
            }

            .lp-mobile-toggle {
                display: block;
            }

            .lp-footer-grid {
                grid-template-columns: 1fr;
            }

            .lp-cta-box {
                padding: 32px 20px;
            }

            .lp-btn span.hide-m {
                display: none;
            }

            .lp-tech-grid {
                grid-template-columns: 1fr;
            }

            .lp-compare {
                grid-template-columns: 1fr;
                gap: 12px;
            }
        }
    </style>
</head>

<body>
    <div class="lp">

        <!-- Aurora Background -->
        <div class="lp-aurora">
            <div class="lp-aurora-orb"></div>
            <div class="lp-aurora-orb"></div>
            <div class="lp-aurora-orb"></div>
        </div>

        <!-- Mouse Glow -->
        <div class="lp-glow" id="lpGlow"></div>

        <!-- Floating Particles -->
        <div class="lp-particles" id="lpParticles"></div>

        <!-- ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ NAVBAR ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ -->
        <nav class="lp-nav" id="lpNav">
            <div class="lp-nav-inner">
                <a href="index.php" class="lp-brand">
                    <span class="lp-brand-icon"><i class="fas fa-graduation-cap"></i></span>
                    <span class="lp-brand-name"><?php echo APP_NAME; ?></span>
                </a>
                <div class="lp-nav-links">
                    <a href="#features">Features</a>
                    <a href="#how-it-works">How It Works</a>
                    <a href="#roles">Portals</a>
                    <a href="#faq">FAQ</a>
                </div>
                <div class="lp-nav-actions">
                    <a class="lp-btn ghost" href="login.php"><i class="fas fa-right-to-bracket"></i> <span class="hide-m">Sign In</span></a>
                    <a class="lp-btn solid" href="register.php"><i class="fas fa-user-plus"></i> <span class="hide-m">Get Started</span></a>
                </div>
                <button class="lp-mobile-toggle" onclick="document.querySelector('.lp-nav-links').classList.toggle('show')" aria-label="Menu"><i class="fas fa-bars"></i></button>
            </div>
        </nav>

        <!-- ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ HERO ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ -->
        <section class="lp-hero">
            <div>
                <div class="lp-hero-badge"><span class="pulse-dot"></span> Next-Gen School Platform</div>
                <h1>Transform Attendance Into <span class="grad" id="heroTyped"></span><span class="typed-cursor">|</span></h1>
                <p class="lp-hero-desc">A unified platform for real-time attendance tracking, analytics dashboards, seamless communication, and complete school operations &mdash; designed for administrators, teachers, students, and parents.</p>
                <div class="lp-hero-cta">
                    <a class="lp-btn solid lg" href="register.php"><i class="fas fa-rocket"></i> Start Free</a>
                    <a class="lp-btn ghost lg" href="#features"><i class="fas fa-play-circle"></i> See How It Works</a>
                </div>
            </div>
            <div class="lp-hero-visual">
                <div class="lp-dashboard-preview" id="lpDashPreview">
                    <div class="lp-dash-glow"></div>
                    <div class="lp-dash-header">
                        <div class="lp-dash-header-left">
                            <div class="lp-dash-dots"><span></span><span></span><span></span></div>
                            <span style="font-size:.82rem;font-weight:600;">Admin Dashboard</span>
                        </div>
                        <span style="font-size:.75rem;opacity:.9;"><span class="lp-live-dot"></span> Live</span>
                    </div>
                    <div class="lp-dash-body">
                        <div class="lp-dash-row">
                            <div class="lp-mini-card">
                                <div class="mc-icon mc-green"><i class="fas fa-user-check"></i></div>
                                <div class="mc-val" data-counter="847">0</div>
                                <div class="mc-lbl">Present Today</div>
                            </div>
                            <div class="lp-mini-card">
                                <div class="mc-icon mc-amber"><i class="fas fa-clock"></i></div>
                                <div class="mc-val" data-counter="23">0</div>
                                <div class="mc-lbl">Late Arrivals</div>
                            </div>
                            <div class="lp-mini-card">
                                <div class="mc-icon mc-red"><i class="fas fa-user-xmark"></i></div>
                                <div class="mc-val" data-counter="12">0</div>
                                <div class="mc-lbl">Absent</div>
                            </div>
                        </div>
                        <div class="lp-mini-chart">
                            <div style="font-size:.78rem;font-weight:600;margin-bottom:10px;">Weekly Attendance Trend</div>
                            <div class="lp-chart-bars">
                                <div class="lp-chart-bar" style="height:85%;animation-delay:.1s"></div>
                                <div class="lp-chart-bar" style="height:92%;animation-delay:.2s"></div>
                                <div class="lp-chart-bar" style="height:78%;animation-delay:.3s"></div>
                                <div class="lp-chart-bar" style="height:95%;animation-delay:.4s"></div>
                                <div class="lp-chart-bar" style="height:88%;animation-delay:.5s"></div>
                                <div class="lp-chart-bar" style="height:91%;animation-delay:.6s"></div>
                                <div class="lp-chart-bar" style="height:96%;animation-delay:.7s"></div>
                            </div>
                            <div style="display:flex;justify-content:space-between;font-size:.65rem;color:var(--text-muted);margin-top:6px;padding:0 4px;">
                                <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ TRUST BAR ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ -->
        <div class="lp-trust sa">
            <span><i class="fas fa-shield-halved"></i> Secure & Encrypted</span>
            <span><i class="fas fa-mobile-screen"></i> PWA Ready</span>
            <span><i class="fas fa-fingerprint"></i> Biometric Support</span>
            <span><i class="fas fa-clock"></i> Real-Time Sync</span>
            <span><i class="fas fa-users"></i> Multi-Role Access</span>
        </div>

        <!-- ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ INTEGRATIONS MARQUEE ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ -->
        <div class="lp-integrations sa">
            <div class="lp-integrations-label">Built with Modern Technologies & Integrations</div>
            <div class="lp-marquee">
                <div class="lp-marquee-track">
                    <div class="lp-marquee-item"><i class="fas fa-fingerprint"></i> Biometric Auth</div>
                    <div class="lp-marquee-item"><i class="fas fa-qrcode"></i> QR Check-in</div>
                    <div class="lp-marquee-item"><i class="fas fa-chart-mixed"></i> Analytics Engine</div>
                    <div class="lp-marquee-item"><i class="fas fa-bell"></i> Push Notifications</div>
                    <div class="lp-marquee-item"><i class="fas fa-robot"></i> AI Assistant</div>
                    <div class="lp-marquee-item"><i class="fas fa-envelope"></i> Email Alerts</div>
                    <div class="lp-marquee-item"><i class="fas fa-database"></i> MySQL</div>
                    <div class="lp-marquee-item"><i class="fab fa-php"></i> PHP 8+</div>
                    <div class="lp-marquee-item"><i class="fas fa-mobile-screen-button"></i> PWA</div>
                    <div class="lp-marquee-item"><i class="fas fa-palette"></i> 11 Themes</div>
                    <div class="lp-marquee-item"><i class="fas fa-lock"></i> CSRF Protection</div>
                    <div class="lp-marquee-item"><i class="fas fa-cloud"></i> Cloud Ready</div>
                    <!-- Duplicate set for seamless loop -->
                    <div class="lp-marquee-item"><i class="fas fa-fingerprint"></i> Biometric Auth</div>
                    <div class="lp-marquee-item"><i class="fas fa-qrcode"></i> QR Check-in</div>
                    <div class="lp-marquee-item"><i class="fas fa-chart-mixed"></i> Analytics Engine</div>
                    <div class="lp-marquee-item"><i class="fas fa-bell"></i> Push Notifications</div>
                    <div class="lp-marquee-item"><i class="fas fa-robot"></i> AI Assistant</div>
                    <div class="lp-marquee-item"><i class="fas fa-envelope"></i> Email Alerts</div>
                    <div class="lp-marquee-item"><i class="fas fa-database"></i> MySQL</div>
                    <div class="lp-marquee-item"><i class="fab fa-php"></i> PHP 8+</div>
                    <div class="lp-marquee-item"><i class="fas fa-mobile-screen-button"></i> PWA</div>
                    <div class="lp-marquee-item"><i class="fas fa-palette"></i> 11 Themes</div>
                    <div class="lp-marquee-item"><i class="fas fa-lock"></i> CSRF Protection</div>
                    <div class="lp-marquee-item"><i class="fas fa-cloud"></i> Cloud Ready</div>
                </div>
            </div>
        </div>

        <!-- ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ METRICS ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ -->
        <div class="lp-metrics">
            <div class="lp-metric sa">
                <div class="lp-metric-icon"><i class="fas fa-user-graduate"></i></div>
                <div class="lp-metric-val" data-counter="<?php echo max($stat_students, 4); ?>">0</div>
                <div class="lp-metric-lbl">Active Students</div>
            </div>
            <div class="lp-metric sa sa-d1">
                <div class="lp-metric-icon"><i class="fas fa-chalkboard-user"></i></div>
                <div class="lp-metric-val" data-counter="<?php echo max($stat_teachers, 2); ?>">0</div>
                <div class="lp-metric-lbl">Dedicated Teachers</div>
            </div>
            <div class="lp-metric sa sa-d2">
                <div class="lp-metric-icon"><i class="fas fa-door-open"></i></div>
                <div class="lp-metric-val" data-counter="<?php echo max($stat_classes, 3); ?>">0</div>
                <div class="lp-metric-lbl">Active Classes</div>
            </div>
            <div class="lp-metric sa sa-d3">
                <div class="lp-metric-icon"><i class="fas fa-chart-line"></i></div>
                <div class="lp-metric-val" data-counter="<?php echo $stat_rate > 0 ? $stat_rate : 99; ?>" data-suffix="%">0</div>
                <div class="lp-metric-lbl">Attendance Rate</div>
            </div>
        </div>

        <!-- ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ FEATURES ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ -->
        <section class="lp-section" id="features">
            <div class="lp-section-head sa">
                <div class="lp-section-badge"><i class="fas fa-sparkles"></i> Platform Features</div>
                <h2>Everything You Need to Run Modern Attendance</h2>
                <p>From biometric check-ins to automated analytics, every module is designed for speed, clarity, and reliability.</p>
            </div>
            <div class="lp-features">
                <article class="lp-feat sa">
                    <div class="lp-feat-icon"><i class="fas fa-fingerprint"></i></div>
                    <h3>Smart Attendance Capture</h3>
                    <p>Multiple check-in methods including biometric scanning, QR codes, and manual entry with complete audit trails.</p>
                </article>
                <article class="lp-feat sa sa-d1">
                    <div class="lp-feat-icon"><i class="fas fa-chart-mixed"></i></div>
                    <h3>Real-Time Analytics</h3>
                    <p>Interactive dashboards with attendance trends, risk detection, and performance metrics updated in real time.</p>
                </article>
                <article class="lp-feat sa sa-d2">
                    <div class="lp-feat-icon"><i class="fas fa-bell"></i></div>
                    <h3>Instant Notifications</h3>
                    <p>Automated alerts for absences, late arrivals, and emergencies delivered to the right stakeholders instantly.</p>
                </article>
                <article class="lp-feat sa sa-d3">
                    <div class="lp-feat-icon"><i class="fas fa-comments"></i></div>
                    <h3>Unified Communication</h3>
                    <p>Built-in messaging, parent-teacher channels, group discussions, and announcement boards in one place.</p>
                </article>
                <article class="lp-feat sa sa-d4">
                    <div class="lp-feat-icon"><i class="fas fa-robot"></i></div>
                    <h3>AI Chat Assistant</h3>
                    <p>Built-in SAMS Bot answers questions about attendance, schedules, grades, and system features 24/7.</p>
                </article>
                <article class="lp-feat sa sa-d5">
                    <div class="lp-feat-icon"><i class="fas fa-mobile-screen-button"></i></div>
                    <h3>Progressive Web App</h3>
                    <p>Install on any device. Works offline, sends push notifications, and provides a native app experience.</p>
                </article>
            </div>
        </section>

        <!-- ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ HOW IT WORKS ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ -->
        <section class="lp-section" id="how-it-works">
            <div class="lp-section-head sa">
                <div class="lp-section-badge"><i class="fas fa-route"></i> How It Works</div>
                <h2>From Check-In to Insight in 4 Steps</h2>
                <p>A streamlined daily workflow that captures data accurately and transforms it into actionable reports.</p>
            </div>
            <div class="lp-steps">
                <div class="lp-step sa">
                    <div class="lp-step-num">1</div>
                    <h4>Check In</h4>
                    <p>Students verify attendance via biometric scan, QR code, or teacher-initiated roll call.</p>
                </div>
                <div class="lp-step sa sa-d1">
                    <div class="lp-step-num">2</div>
                    <h4>Live Monitoring</h4>
                    <p>Dashboards update in real time showing present, absent, and late students across all classes.</p>
                </div>
                <div class="lp-step sa sa-d2">
                    <div class="lp-step-num">3</div>
                    <h4>Auto Alerts</h4>
                    <p>Parents and administrators receive instant notifications for absences and attendance anomalies.</p>
                </div>
                <div class="lp-step sa sa-d3">
                    <div class="lp-step-num">4</div>
                    <h4>Reports & Insights</h4>
                    <p>Generate detailed attendance reports, trend analysis, and exportable data for decision making.</p>
                </div>
            </div>
        </section>

        <!-- ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ ROLE PORTALS ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ -->
        <section class="lp-section" id="roles">
            <div class="lp-section-head sa">
                <div class="lp-section-badge"><i class="fas fa-user-shield"></i> Role-Based Access</div>
                <h2>Tailored Workspaces for Every Role</h2>
                <p>Each portal is purpose-built with only the tools and views relevant to that user's responsibilities.</p>
            </div>
            <div class="lp-roles">
                <article class="lp-role sa">
                    <div class="lp-role-icon r-blue"><i class="fas fa-user-tie"></i></div>
                    <h3>Administrators</h3>
                    <p>Complete oversight of all school operations, users, and system configuration.</p>
                    <ul>
                        <li>User & role management</li>
                        <li>Institution-wide analytics</li>
                        <li>System settings & security</li>
                        <li>Report generation</li>
                    </ul>
                </article>
                <article class="lp-role sa sa-d1">
                    <div class="lp-role-icon r-green"><i class="fas fa-chalkboard-user"></i></div>
                    <h3>Teachers</h3>
                    <p>Efficient class management with tools for attendance, grading, and parent communication.</p>
                    <ul>
                        <li>Class attendance marking</li>
                        <li>Materials & gradebook</li>
                        <li>Student performance reports</li>
                        <li>Parent coordination</li>
                    </ul>
                </article>
                <article class="lp-role sa sa-d2">
                    <div class="lp-role-icon r-purple"><i class="fas fa-user-graduate"></i></div>
                    <h3>Students</h3>
                    <p>Personal dashboard with attendance history, assignments, and communication tools.</p>
                    <ul>
                        <li>Attendance check-in</li>
                        <li>Schedule & assignments</li>
                        <li>Grade tracking</li>
                        <li>Messaging & notices</li>
                    </ul>
                </article>
                <article class="lp-role sa sa-d3">
                    <div class="lp-role-icon r-amber"><i class="fas fa-people-roof"></i></div>
                    <h3>Parents</h3>
                    <p>Stay connected with real-time visibility into your child's attendance and school life.</p>
                    <ul>
                        <li>Child attendance monitoring</li>
                        <li>Progress & grade views</li>
                        <li>Teacher meetings</li>
                        <li>Fee tracking & notices</li>
                    </ul>
                </article>
            </div>
        </section>

        <!-- ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ TESTIMONIALS ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ -->
        <section class="lp-section">
            <div class="lp-section-head sa">
                <div class="lp-section-badge"><i class="fas fa-quote-left"></i> Testimonials</div>
                <h2>Trusted by Schools Everywhere</h2>
                <p>See what administrators, teachers, and parents are saying about the platform.</p>
            </div>
            <div class="lp-testimonials">
                <div class="lp-testimonial sa">
                    <div class="lp-testimonial-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                    <blockquote>"This system transformed how we track attendance. The real-time dashboards give us instant visibility, and the automated alerts have reduced truancy by 40%."</blockquote>
                    <div class="lp-testimonial-author">
                        <div class="lp-testimonial-avatar" style="background: linear-gradient(135deg, #4f46e5, #6366f1);">JM</div>
                        <div class="lp-testimonial-info"><strong>James Mitchell</strong><span>School Administrator</span></div>
                    </div>
                </div>
                <div class="lp-testimonial sa sa-d1">
                    <div class="lp-testimonial-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                    <blockquote>"Roll call used to take 10 minutes per class. Now with QR check-in, it's done in seconds. The parent communication tools are amazing too."</blockquote>
                    <div class="lp-testimonial-author">
                        <div class="lp-testimonial-avatar" style="background: linear-gradient(135deg, #16a34a, #22c55e);">SP</div>
                        <div class="lp-testimonial-info"><strong>Sarah Patel</strong><span>Mathematics Teacher</span></div>
                    </div>
                </div>
                <div class="lp-testimonial sa sa-d2">
                    <div class="lp-testimonial-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-stroke"></i></div>
                    <blockquote>"As a parent, I love getting instant notifications when my children check in. The progress tracking and teacher messaging keep me fully in the loop."</blockquote>
                    <div class="lp-testimonial-author">
                        <div class="lp-testimonial-avatar" style="background: linear-gradient(135deg, #d97706, #f59e0b);">RK</div>
                        <div class="lp-testimonial-info"><strong>Rachel Kim</strong><span>Parent</span></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ FAQ ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ -->
        <section class="lp-section" id="faq">
            <div class="lp-section-head sa">
                <div class="lp-section-badge"><i class="fas fa-circle-question"></i> FAQ</div>
                <h2>Frequently Asked Questions</h2>
                <p>Quick answers to common questions about the platform.</p>
            </div>
            <div class="lp-faq">
                <div class="lp-faq-item sa">
                    <button class="lp-faq-q" onclick="this.parentElement.classList.toggle('active')">What attendance methods are supported? <i class="fas fa-chevron-down"></i></button>
                    <div class="lp-faq-a">
                        <p>The platform supports biometric fingerprint scanning, QR code check-in, manual roll call by teachers, and self-check-in through the student portal. All methods include verification and audit trail logging.</p>
                    </div>
                </div>
                <div class="lp-faq-item sa sa-d1">
                    <button class="lp-faq-q" onclick="this.parentElement.classList.toggle('active')">Is the system accessible on mobile devices? <i class="fas fa-chevron-down"></i></button>
                    <div class="lp-faq-a">
                        <p>Yes. The platform is built as a Progressive Web App (PWA) that can be installed on any smartphone or tablet. It works offline and provides push notifications for real-time alerts.</p>
                    </div>
                </div>
                <div class="lp-faq-item sa sa-d1">
                    <button class="lp-faq-q" onclick="this.parentElement.classList.toggle('active')">How are parents notified about absences? <i class="fas fa-chevron-down"></i></button>
                    <div class="lp-faq-a">
                        <p>Parents receive instant in-app notifications when their child is marked absent or arrives late. They can also view detailed attendance history and trends through their dedicated portal.</p>
                    </div>
                </div>
                <div class="lp-faq-item sa sa-d2">
                    <button class="lp-faq-q" onclick="this.parentElement.classList.toggle('active')">Can I customize the look and feel? <i class="fas fa-chevron-down"></i></button>
                    <div class="lp-faq-a">
                        <p>Absolutely. The platform includes 11 built-in themes ranging from professional corporate styles to dark mode. You can switch themes instantly from the sidebar without any page reload.</p>
                    </div>
                </div>
                <div class="lp-faq-item sa sa-d2">
                    <button class="lp-faq-q" onclick="this.parentElement.classList.toggle('active')">What roles and permissions are available? <i class="fas fa-chevron-down"></i></button>
                    <div class="lp-faq-a">
                        <p>Four distinct roles are supported: Administrator (full system control), Teacher (class management), Student (personal dashboard), and Parent (child monitoring). Each role sees only the features relevant to their responsibilities.</p>
                    </div>
                </div>
                <div class="lp-faq-item sa sa-d3">
                    <button class="lp-faq-q" onclick="this.parentElement.classList.toggle('active')">Is my data secure? <i class="fas fa-chevron-down"></i></button>
                    <div class="lp-faq-a">
                        <p>Yes. All data is encrypted, passwords are hashed with bcrypt, sessions are protected against CSRF and fixation attacks, and all inputs are sanitized against SQL injection and XSS. Role-based access ensures users only see their own data.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ BEFORE & AFTER COMPARISON ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ -->
        <section class="lp-section">
            <div class="lp-section-head sa">
                <div class="lp-section-badge"><i class="fas fa-arrows-left-right"></i> Comparison</div>
                <h2>Traditional vs. SAMS-Powered Attendance</h2>
                <p>See the measurable difference when you switch from manual processes to our intelligent system.</p>
            </div>
            <div class="lp-compare sa">
                <div class="lp-compare-col lp-compare-old">
                    <div class="lp-compare-header"><i class="fas fa-times-circle"></i> Without SAMS</div>
                    <ul>
                        <li><i class="fas fa-xmark"></i> Manual pen-and-paper roll call</li>
                        <li><i class="fas fa-xmark"></i> Reports compiled weekly or monthly</li>
                        <li><i class="fas fa-xmark"></i> Parents informed days after absences</li>
                        <li><i class="fas fa-xmark"></i> No visibility into attendance trends</li>
                        <li><i class="fas fa-xmark"></i> Scattered communication channels</li>
                        <li><i class="fas fa-xmark"></i> Hours spent on manual data entry</li>
                    </ul>
                </div>
                <div class="lp-compare-divider sa sa-d1">
                    <div class="lp-compare-vs">VS</div>
                </div>
                <div class="lp-compare-col lp-compare-new">
                    <div class="lp-compare-header"><i class="fas fa-check-circle"></i> With SAMS</div>
                    <ul>
                        <li><i class="fas fa-check"></i> Digital biometric & QR check-in</li>
                        <li><i class="fas fa-check"></i> Real-time dashboards & analytics</li>
                        <li><i class="fas fa-check"></i> Instant automated parent alerts</li>
                        <li><i class="fas fa-check"></i> AI-powered risk detection</li>
                        <li><i class="fas fa-check"></i> Unified messaging & notices</li>
                        <li><i class="fas fa-check"></i> Zero manual data entry needed</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ SECURITY & TECHNOLOGY ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ -->
        <section class="lp-section">
            <div class="lp-section-head sa">
                <div class="lp-section-badge"><i class="fas fa-shield-halved"></i> Security & Technology</div>
                <h2>Enterprise-Grade Security, School-Friendly Design</h2>
                <p>Built with modern security practices and cutting-edge web technologies.</p>
            </div>
            <div class="lp-tech-grid">
                <div class="lp-tech-card sa">
                    <div class="lp-tech-icon"><i class="fas fa-lock"></i></div>
                    <h4>Encrypted Sessions</h4>
                    <p>Bcrypt password hashing, CSRF protection, and secure session management.</p>
                </div>
                <div class="lp-tech-card sa sa-d1">
                    <div class="lp-tech-icon"><i class="fas fa-shield-check"></i></div>
                    <h4>Input Sanitization</h4>
                    <p>Prepared SQL statements and XSS filtering on every user input.</p>
                </div>
                <div class="lp-tech-card sa sa-d2">
                    <div class="lp-tech-icon"><i class="fas fa-user-lock"></i></div>
                    <h4>Role-Based Access</h4>
                    <p>Granular permissions ensure users only access their own data and features.</p>
                </div>
                <div class="lp-tech-card sa sa-d3">
                    <div class="lp-tech-icon"><i class="fas fa-database"></i></div>
                    <h4>Audit Logging</h4>
                    <p>Complete activity audit trail for compliance and accountability.</p>
                </div>
                <div class="lp-tech-card sa sa-d4">
                    <div class="lp-tech-icon"><i class="fas fa-cloud-arrow-down"></i></div>
                    <h4>PWA & Offline</h4>
                    <p>Installable app with service workers for seamless offline capability.</p>
                </div>
                <div class="lp-tech-card sa sa-d5">
                    <div class="lp-tech-icon"><i class="fas fa-palette"></i></div>
                    <h4>11 Premium Themes</h4>
                    <p>Customizable UI with instant theme switching and dark mode support.</p>
                </div>
            </div>
        </section>

        <!-- ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ CTA ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ -->
        <section class="lp-cta">
            <div class="lp-cta-box sa">
                <h2>Ready to Modernize Your School's Attendance?</h2>
                <p>Join schools already using the platform to streamline operations and improve student outcomes.</p>
                <div class="lp-cta-actions">
                    <a class="lp-btn white lg" href="register.php"><i class="fas fa-rocket"></i> Create Free Account</a>
                    <a class="lp-btn outline-w lg" href="login.php"><i class="fas fa-right-to-bracket"></i> Sign In</a>
                </div>
            </div>
        </section>

        <!-- ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ FOOTER ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ -->
        <footer class="lp-footer">
            <div class="lp-footer-grid">
                <div>
                    <a href="index.php" class="lp-footer-brand">
                        <span class="lp-footer-brand-icon"><i class="fas fa-graduation-cap"></i></span>
                        <span class="lp-footer-brand-name"><?php echo APP_NAME; ?></span>
                    </a>
                    <p class="lp-footer-desc">A comprehensive school attendance management system with real-time analytics, multi-role portals, and seamless communication.</p>
                    <div class="lp-footer-social">
                        <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" aria-label="GitHub"><i class="fab fa-github"></i></a>
                        <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div>
                    <h4>Platform</h4>
                    <ul class="lp-footer-links">
                        <li><a href="#features">Features</a></li>
                        <li><a href="#how-it-works">How It Works</a></li>
                        <li><a href="#roles">Role Portals</a></li>
                        <li><a href="#faq">FAQ</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Access</h4>
                    <ul class="lp-footer-links">
                        <li><a href="login.php">Sign In</a></li>
                        <li><a href="register.php">Create Account</a></li>
                        <li><a href="forgot-password.php">Reset Password</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Support</h4>
                    <ul class="lp-footer-links">
                        <li><a href="system-overview.php">System Overview</a></li>
                        <li><a href="notices.php">Notices</a></li>
                        <li><a href="forum/">Community Forum</a></li>
                    </ul>
                </div>
            </div>
            <div class="lp-footer-bottom">
                &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved. Built with <i class="fas fa-heart" style="color:#ef4444;font-size:.7rem;"></i> for modern schools.
            </div>
        </footer>

    </div>

    <!-- ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ SCRIPTS ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ -->
    <script>
        // Navbar scroll effect
        const nav = document.getElementById('lpNav');
        window.addEventListener('scroll', () => {
            nav.classList.toggle('scrolled', window.scrollY > 20);
        }, {
            passive: true
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(a => {
            a.addEventListener('click', e => {
                const t = document.querySelector(a.getAttribute('href'));
                if (!t) return;
                e.preventDefault();
                t.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            });
        });

        // Scroll reveal animations
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -40px 0px'
        });

        document.querySelectorAll('.sa').forEach(el => observer.observe(el));

        // Animated counters
        const counterObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                const el = entry.target;
                const target = parseInt(el.dataset.counter);
                if (!target) return;
                const suffix = el.dataset.suffix || '';
                const duration = 1200;
                const start = performance.now();
                const animate = (now) => {
                    const progress = Math.min((now - start) / duration, 1);
                    const eased = 1 - Math.pow(1 - progress, 3);
                    el.textContent = Math.round(target * eased) + suffix;
                    if (progress < 1) requestAnimationFrame(animate);
                };
                requestAnimationFrame(animate);
                counterObserver.unobserve(el);
            });
        }, {
            threshold: 0.5
        });

        // Typewriter effect
        (function() {
            const words = ['Actionable Intelligence', 'Real-Time Insights', 'Smarter Schools', 'Better Outcomes'];
            const el = document.getElementById('heroTyped');
            if (!el) return;
            let wordIndex = 0,
                charIndex = 0,
                isDeleting = false;

            function type() {
                const current = words[wordIndex];
                el.textContent = current.substring(0, charIndex);
                if (!isDeleting && charIndex === current.length) {
                    setTimeout(() => {
                        isDeleting = true;
                        type();
                    }, 2200);
                    return;
                }
                if (isDeleting && charIndex === 0) {
                    isDeleting = false;
                    wordIndex = (wordIndex + 1) % words.length;
                }
                charIndex += isDeleting ? -1 : 1;
                setTimeout(type, isDeleting ? 40 : 80);
            }
            type();
        })();

        // Floating particles
        (function() {
            const container = document.getElementById('lpParticles');
            if (!container) return;
            for (let i = 0; i < 12; i++) {
                const p = document.createElement('div');
                p.className = 'lp-particle';
                const size = Math.random() * 6 + 3;
                p.style.width = size + 'px';
                p.style.height = size + 'px';
                p.style.left = Math.random() * 100 + '%';
                p.style.animationDuration = (Math.random() * 20 + 15) + 's';
                p.style.animationDelay = (Math.random() * 10) + 's';
                container.appendChild(p);
            }
        })();

        document.querySelectorAll('[data-counter]').forEach(el => counterObserver.observe(el));

        // Mouse glow follow
        (function() {
            const glow = document.getElementById('lpGlow');
            if (!glow) return;
            let mouseX = -500, mouseY = -500;
            document.addEventListener('mousemove', e => {
                mouseX = e.clientX;
                mouseY = e.clientY;
            }, { passive: true });
            function updateGlow() {
                glow.style.left = mouseX + 'px';
                glow.style.top = mouseY + 'px';
                requestAnimationFrame(updateGlow);
            }
            updateGlow();
        })();

        // 3D tilt on dashboard preview
        (function() {
            const card = document.getElementById('lpDashPreview');
            if (!card) return;
            card.addEventListener('mousemove', e => {
                const rect = card.getBoundingClientRect();
                const x = (e.clientX - rect.left) / rect.width;
                const y = (e.clientY - rect.top) / rect.height;
                card.style.setProperty('--rx', ((y - .5) * -12) + 'deg');
                card.style.setProperty('--ry', ((x - .5) * 12) + 'deg');
                card.style.setProperty('--gx', (x * 100) + '%');
                card.style.setProperty('--gy', (y * 100) + '%');
                card.classList.add('tilting');
            });
            card.addEventListener('mouseleave', () => {
                card.classList.remove('tilting');
                card.style.setProperty('--rx', '0deg');
                card.style.setProperty('--ry', '0deg');
            });
        })();

        // Mobile nav toggle (show/hide links on small screens)
        const style = document.createElement('style');
        style.textContent = '.lp-nav-links.show { display: flex !important; position: absolute; top: 100%; left: 0; right: 0; background: var(--bg-white); border-bottom: 1px solid var(--border-color); padding: 12px 24px; flex-direction: column; box-shadow: 0 8px 24px rgba(0,0,0,.08); }';
        document.head.appendChild(style);
    </script>
</body>

</html>
