import React, { useState, useEffect } from 'react'
import { motion } from 'framer-motion'
import { Search, Download, Activity, LogIn, Edit, Calendar } from 'lucide-react'
import { t } from '../utils/translations'
import './Audit.css'

const Audit = ({ language = 'en' }) => {
  const [logs, setLogs] = useState([])
  const [stats, setStats] = useState({ total: 0, logins: 0, updates: 0, today: 0 })
  const [loading, setLoading] = useState(true)
  const [searchTerm, setSearchTerm] = useState('')
  const [filterType, setFilterType] = useState('all')

  useEffect(() => {
    fetch('/uams/backend/api/audit.php')
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setLogs(data.logs)
          setStats(data.stats)
        }
        setLoading(false)
      })
      .catch(err => {
        console.error('Error loading audit logs:', err)
        setLoading(false)
      })
  }, [])

  const filteredLogs = logs.filter(log => {
    const matchesSearch =
      log.full_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      log.action_type?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      log.action_details?.toLowerCase().includes(searchTerm.toLowerCase())

    const matchesFilter = filterType === 'all' || log.action_type === filterType

    return matchesSearch && matchesFilter
  })

  const getActionIcon = (actionType) => {
    switch(actionType) {
      case 'Login': return <LogIn size={16} />
      case 'Update': return <Edit size={16} />
      case 'Create': return <Activity size={16} />
      case 'Delete': return <Activity size={16} />
      case 'Export': return <Download size={16} />
      default: return <Activity size={16} />
    }
  }

  const getActionClass = (actionType) => {
    switch(actionType) {
      case 'Login': return 'login'
      case 'Update': return 'update'
      case 'Create': return 'create'
      case 'Delete': return 'delete'
      case 'Export': return 'export'
      default: return 'default'
    }
  }

  if (loading) {
    return (
      <div className="audit-content">
        <div className="loading-spinner">{t('loading', language)}</div>
      </div>
    )
  }

  return (
    <div className="audit-content">
      <motion.div
        initial={{ opacity: 0, y: -20 }}
        animate={{ opacity: 1, y: 0 }}
      >
        <h2 className="page-title">{t('auditLog', language)}</h2>
        <p className="page-subtitle">{t('trackActivities', language)}</p>
      </motion.div>

      <div className="audit-stats">
        <motion.div
          className="audit-stat-card"
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.1 }}
        >
          <Activity size={24} className="stat-icon" />
          <div className="stat-content">
            <div className="stat-value">{stats.total}</div>
            <div className="stat-label">{t('totalEntries', language)}</div>
          </div>
        </motion.div>

        <motion.div
          className="audit-stat-card"
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.2 }}
        >
          <LogIn size={24} className="stat-icon login" />
          <div className="stat-content">
            <div className="stat-value">{stats.logins}</div>
            <div className="stat-label">{t('logins', language)}</div>
          </div>
        </motion.div>

        <motion.div
          className="audit-stat-card"
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.3 }}
        >
          <Edit size={24} className="stat-icon update" />
          <div className="stat-content">
            <div className="stat-value">{stats.updates}</div>
            <div className="stat-label">{t('updates', language)}</div>
          </div>
        </motion.div>

        <motion.div
          className="audit-stat-card"
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.4 }}
        >
          <Calendar size={24} className="stat-icon today" />
          <div className="stat-content">
            <div className="stat-value">{stats.today}</div>
            <div className="stat-label">{t('today', language)}</div>
          </div>
        </motion.div>
      </div>

      <motion.div
        className="audit-table-section"
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.5 }}
      >
        <div className="table-header">
          <div className="search-box">
            <Search size={20} />
            <input
              type="text"
              placeholder={t('searchAudit', language)}
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </div>
          <select
            className="filter-select"
            value={filterType}
            onChange={(e) => setFilterType(e.target.value)}
          >
            <option value="all">{t('filterAllTypes', language)}</option>
            <option value="Login">{t('actionLogin', language)}</option>
            <option value="Create">{t('actionCreate', language)}</option>
            <option value="Update">{t('actionUpdate', language)}</option>
            <option value="Delete">{t('actionDelete', language)}</option>
            <option value="Export">{t('actionExport', language)}</option>
          </select>
          <button className="btn-primary">
            <Download size={18} />
            {t('exportLogs', language)}
          </button>
        </div>

        <div className="table-container">
          <table className="audit-table">
            <thead>
              <tr>
                <th>{t('colTimestamp', language)}</th>
                <th>{t('colUser', language)}</th>
                <th>{t('colAction', language)}</th>
                <th>{t('colDetails', language)}</th>
                <th>{t('colIpAddress', language)}</th>
              </tr>
            </thead>
            <tbody>
              {filteredLogs.map((log, index) => (
                <motion.tr
                  key={log.log_id}
                  initial={{ opacity: 0, x: -20 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ delay: index * 0.03 }}
                >
                  <td className="timestamp">
                    {new Date(log.created_at).toLocaleString(language === 'en' ? 'en-US' : language === 'kz' ? 'kk-KZ' : 'ru-RU')}
                  </td>
                  <td>
                    <div className="user-cell">
                      <div className="user-avatar">{log.full_name?.charAt(0) || '?'}</div>
                      <div>
                        <div className="user-name">{log.full_name || t('unknownUser', language)}</div>
                        <div className="user-id">ID: {log.user_id}</div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span className={`action-badge ${getActionClass(log.action_type)}`}>
                      {getActionIcon(log.action_type)}
                      {t('action' + log.action_type, language) || log.action_type}
                    </span>
                  </td>
                  <td className="details">{log.action_details}</td>
                  <td className="ip-address">{log.ip_address || 'N/A'}</td>
                </motion.tr>
              ))}
            </tbody>
          </table>
        </div>
      </motion.div>
    </div>
  )
}

export default Audit
