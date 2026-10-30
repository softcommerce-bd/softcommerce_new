  {{-- <style>
        .page-side-nav {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #eef2f6;
            transition: all 0.2s;
        }

        .page-side-nav .nav-link {
            color: #475569;
            font-size: 0.875rem;
            border-radius: 10px;
            transition: all 0.2s ease;
            padding: 0.7rem 1rem;
            margin-bottom: 4px;
            font-weight: 500;
        }

        .page-side-nav .nav-link i {
            width: 24px;
            font-size: 1.2rem;
        }

        .page-side-nav .nav-link:hover {
            background: #f1f5f9;
            color: #3b82f6;
            transform: translateX(4px);
        }

        .page-side-nav .nav-link.active {
            background: linear-gradient(95deg, #eff6ff 0%, #e0f2fe 100%);
            color: #2563eb;
            font-weight: 600;
            border-left: 3px solid #2563eb;
        }

        .module-header {
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 0.1em;
            color: #94a3b8;
            margin-top: 1rem;
        }

        /* Cards */
        .stat-card {
            background: white;
            border-radius: 20px;
            border: none;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 2px 6px rgba(0,0,0,0.03), 0 1px 2px rgba(0,0,0,0.05);
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 20px -12px rgba(0,0,0,0.15);
        }

        .gradient-primary {
            background: linear-gradient(145deg, #4361ee, #3a0ca3);
        }
        .gradient-success {
            background: linear-gradient(145deg, #06d6a0, #028090);
        }
        .gradient-warning {
            background: linear-gradient(145deg, #f8961e, #f3722c);
        }

        .data-table-card {
            border-radius: 20px;
            overflow: hidden;
            border: none;
            box-shadow: 0 8px 20px rgba(0,0,0,0.02), 0 2px 4px rgba(0,0,0,0.05);
        }

        .data-table-card .card-header {
            background: rgba(255,255,255,0.95);
            border-bottom: 1px solid #f0f2f5;
            padding: 1rem 1.5rem;
        }

        .badge-status {
            padding: 0.35rem 0.75rem;
            border-radius: 100px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .btn-outline-table {
            border-radius: 40px;
            padding: 0.2rem 0.6rem;
            font-size: 0.75rem;
        }
    </style> --}}

    <style>
/* Stats Cards */
.stat-card {
    border-radius: 12px;
    padding: 1.25rem;
    transition: all 0.3s ease;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.bg-gradient-danger {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.bg-gradient-success {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.bg-gradient-info {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}

.opacity-50 {
    opacity: 0.5;
}

/* Side Navigation */
.page-side-nav {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    overflow: hidden;
}

.page-side-nav .module-header {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #8c98a9;
    padding: 0.75rem 1rem;
    margin-top: 0.5rem;
}

.page-side-nav .nav-link {
    color: #4a5568;
    padding: 0.625rem 1rem;
    border-radius: 8px;
    margin: 2px 0;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.2s ease;
}

.page-side-nav .nav-link i {
    width: 22px;
    font-size: 1.1rem;
}

.page-side-nav .nav-link:hover {
    background: #f7fafc;
    color: #2d3748;
}

.page-side-nav .nav-link.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.page-side-nav .nav-link .badge {
    margin-left: auto;
}

/* Data Table Card */
.data-table-card {
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border: none;
    overflow: hidden;
}

.data-table-card .card-header {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 1rem 1.25rem;
    font-weight: 600;
}

.data-table-card table {
    margin-bottom: 0;
}

.data-table-card table th {
    background: #f8fafc;
    border-top: none;
    font-weight: 600;
    font-size: 13px;
    color: #4a5568;
}

/* Tree View for Chart of Accounts */
.tree-view {
    font-size: 14px;
}

.tree-view .tree-item {
    padding: 8px 0;
    border-bottom: 1px solid #edf2f7;
}

.tree-view .tree-item .tree-toggle {
    cursor: pointer;
    margin-right: 8px;
    color: #718096;
}

.tree-view .tree-item .tree-label {
    cursor: pointer;
}



.tree-view .tree-children {
    margin-left: 28px;
}

/* Status Badges */
.badge-active {
    background: #48bb78;
    color: white;
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 11px;
}

.badge-inactive {
    background: #f56565;
    color: white;
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 11px;
}

.badge-paid, .badge-received, .badge-completed {
    background: #48bb78;
    color: white;
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 11px;
}

.badge-pending {
    background: #ed8936;
    color: white;
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 11px;
}

.badge-partial {
    background: #4299e1;
    color: white;
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 11px;
}

.badge-draft {
    background: #a0aec0;
    color: white;
}

.badge-approved {
    background: #4299e1;
    color: white;
}

.badge-posted {
    background: #48bb78;
    color: white;
}

/* Modal Styles */
.modal-content {
    border-radius: 16px;
    border: none;
}

.modal-header {
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
    border-radius: 16px 16px 0 0;
}

/* Form Controls */
.form-control, .form-select {
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    padding: 0.5rem 0.75rem;
}

.form-control:focus, .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.btn {
    border-radius: 8px;
    padding: 0.5rem 1rem;
    font-weight: 500;
}


.btn-soft-primary:hover {
    background: rgba(102, 126, 234, 0.2);
}

/* Balance Sheet Styles */
.balance-sheet-card {
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 1.5rem;
}

.balance-sheet-row {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e2e8f0;
}

.balance-sheet-row.total {
    background: #f8fafc;
    font-weight: bold;
    border-top: 2px solid #e2e8f0;
}

/* Responsive */
@media (max-width: 768px) {
    .page-side-nav {
        width: 100% !important;
    }
    
    .stat-card h3 {
        font-size: 1.25rem;
    }
}

/* Account Row Styles */
.account-row-asset {
    border-left: 3px solid #48bb78;
}

.account-row-liability {
    border-left: 3px solid #f56565;
}

.account-row-equity {
    border-left: 3px solid #4299e1;
}

.account-row-income {
    border-left: 3px solid #ed8936;
}

.account-row-expense {
    border-left: 3px solid #9f7aea;
}
</style>