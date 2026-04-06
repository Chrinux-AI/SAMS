<style>
  .settings-container {
    padding: 2rem;
    max-width: 1200px;
    margin: 0 auto;
  }

  .settings-grid {
    display: grid;
    gap: 2rem;
  }

  .settings-card {
    background: var(--card-bg, var(--bg-white));
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 2rem;
    box-shadow: var(--shadow);
    animation: fadeIn 0.6s ease-out;
  }

  .settings-card h2 {
    color: var(--primary);
    margin-bottom: 1.5rem;
    font-size: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .form-group {
    margin-bottom: 1.5rem;
  }

  .form-group label {
    display: block;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    font-weight: 500;
  }

  .form-control {
    width: 100%;
    padding: 0.75rem;
    background: var(--bg-secondary, var(--gray-100));
    border: 1px solid var(--border-color);
    border-radius: 6px;
    color: var(--text-primary);
    font-size: 1rem;
    transition: all 0.3s;
  }

  .form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
  }

  .btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
  }

  .btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
  }

  .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
  }

  .alert {
    padding: 1rem;
    border-radius: 6px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .alert-success {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid #10b981;
    color: #10b981;
  }

  .alert-error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid #ef4444;
    color: #ef4444;
  }

  .toggle-switch {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 30px;
  }

  .toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
  }

  .toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 30px;
  }

  .toggle-slider:before {
    position: absolute;
    content: "";
    height: 22px;
    width: 22px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
  }

  input:checked+.toggle-slider {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
  }

  input:checked+.toggle-slider:before {
    transform: translateX(30px);
  }

  .profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    color: white;
    margin: 0 auto 1.5rem;
  }

  .stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 1.5rem;
  }

  .stat-box {
    background: var(--bg-secondary, var(--gray-100));
    padding: 1rem;
    border-radius: 6px;
    text-align: center;
  }

  .stat-box h3 {
    color: var(--primary);
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
  }

  .stat-box p {
    color: var(--text-secondary);
    font-size: 0.875rem;
  }

  .role-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
    text-transform: uppercase;
  }

  .role-admin {
    background: #ef4444;
    color: white;
  }

  .role-teacher {
    background: #3b82f6;
    color: white;
  }

  .role-student {
    background: #10b981;
    color: white;
  }

  .role-parent {
    background: #f59e0b;
    color: white;
  }

  .role-librarian {
    background: #8b5cf6;
    color: white;
  }

  .role-bursar {
    background: #06b6d4;
    color: white;
  }

  .role-accountant {
    background: #0891b2;
    color: white;
  }

  .role-forum_moderator {
    background: #d946ef;
    color: white;
  }

  .role-transport {
    background: #65a30d;
    color: white;
  }

  .role-general {
    background: #64748b;
    color: white;
  }

  @keyframes fadeIn {
    from {
      opacity: 0;
      transform: translateY(20px);
    }

    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
</style>
