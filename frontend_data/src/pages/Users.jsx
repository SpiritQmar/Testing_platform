import React, { useState, useEffect } from 'react'
import { motion } from 'framer-motion'
import { Search, Edit, Trash2, UserPlus, Shield, User, CheckCircle, XCircle } from 'lucide-react'
import { t } from '../utils/translations'
import './Users.css'

const Users = ({ language = 'en' }) => {
  const [users, setUsers] = useState([])
  const [loading, setLoading] = useState(true)
  const [searchTerm, setSearchTerm] = useState('')

  useEffect(() => {
    fetch('/uams/backend/api/users.php')
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setUsers(data.users)
        }
        setLoading(false)
      })
      .catch(err => {
        console.error('Error loading users:', err)
        setLoading(false)
      })
  }, [])

  const filteredUsers = users.filter(user =>
    user.full_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    user.email.toLowerCase().includes(searchTerm.toLowerCase()) ||
    user.role_name.toLowerCase().includes(searchTerm.toLowerCase())
  )

  const stats = {
    total: users.length,
    active: users.filter(u => u.is_active === 1).length,
    admins: users.filter(u => u.role_name === 'superadmin').length
  }

  if (loading) {
    return (
      <div className="users-content">
        <div className="loading-spinner">{t('loading', language)}</div>
      </div>
    )
  }

  return (
    <div className="users-content">
      <motion.div
        initial={{ opacity: 0, y: -20 }}
        animate={{ opacity: 1, y: 0 }}
      >
        <h2 className="page-title">{t('userManagement', language)}</h2>
        <p className="page-subtitle">{t('manageUsers', language)}</p>
      </motion.div>

      <div className="users-stats">
        <motion.div
          className="user-stat-card"
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.1 }}
        >
          <User size={24} className="stat-icon" />
          <div className="stat-content">
            <div className="stat-value">{stats.total}</div>
            <div className="stat-label">{t('totalUsers', language)}</div>
          </div>
        </motion.div>

        <motion.div
          className="user-stat-card"
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.2 }}
        >
          <CheckCircle size={24} className="stat-icon active" />
          <div className="stat-content">
            <div className="stat-value">{stats.active}</div>
            <div className="stat-label">{t('activeUsers', language)}</div>
          </div>
        </motion.div>

        <motion.div
          className="user-stat-card"
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.3 }}
        >
          <Shield size={24} className="stat-icon admin" />
          <div className="stat-content">
            <div className="stat-value">{stats.admins}</div>
            <div className="stat-label">{t('administrators', language)}</div>
          </div>
        </motion.div>
      </div>

      <motion.div
        className="users-table-section"
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.4 }}
      >
        <div className="table-header">
          <div className="search-box">
            <Search size={20} />
            <input
              type="text"
              placeholder="Search users by name, email, or role..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </div>
          <button className="btn-primary">
            <UserPlus size={18} />
            Add User
          </button>
        </div>

        <div className="table-container">
          <table className="users-table">
            <thead>
              <tr>
                <th>User</th>
                <th>Role</th>
                <th>Status</th>
                <th>Last Login</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {filteredUsers.map((user, index) => (
                <motion.tr
                  key={user.user_id}
                  initial={{ opacity: 0, x: -20 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ delay: index * 0.05 }}
                >
                  <td>
                    <div className="user-cell">
                      <div className="user-avatar">{user.full_name.charAt(0)}</div>
                      <div>
                        <div className="user-name">{user.full_name}</div>
                        <div className="user-email">{user.email}</div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span className={`role-badge ${user.role_name}`}>
                      {user.role_name}
                    </span>
                  </td>
                  <td>
                    <span className={`status-badge ${user.is_active ? 'active' : 'inactive'}`}>
                      {user.is_active ? 'Active' : 'Inactive'}
                    </span>
                  </td>
                  <td className="last-login">
                    {user.last_login || 'Never'}
                  </td>
                  <td>
                    <div className="action-buttons">
                      <button className="btn-icon" title="Edit">
                        <Edit size={16} />
                      </button>
                      <button className="btn-icon danger" title="Delete">
                        <Trash2 size={16} />
                      </button>
                    </div>
                  </td>
                </motion.tr>
              ))}
            </tbody>
          </table>
        </div>
      </motion.div>
    </div>
  )
}

export default Users
