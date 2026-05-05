import React, { useState } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { LayoutDashboard, FileQuestion, Upload, Settings, Users, Activity, User, LogOut, ChevronUp } from 'lucide-react'
import { t } from '../utils/translations'
import './Sidebar.css'

const Sidebar = ({ currentPage, setCurrentPage, user, language = 'en' }) => {
  const [showUserMenu, setShowUserMenu] = useState(false)

  const menuItems = [
    { id: 'dashboard', icon: LayoutDashboard, label: t('overview', language) },
    { id: 'questions', icon: FileQuestion, label: t('aiAnalytics', language) },
    { id: 'import', icon: Upload, label: t('import', language) },
    { id: 'settings', icon: Settings, label: t('settings', language) },
    { id: 'users', icon: Users, label: t('users', language) },
    { id: 'audit', icon: Activity, label: t('audit', language) }
  ]

  const handleLogout = async () => {
    await fetch('logout.php')
    window.location.href = 'login.php'
  }

  return (
    <motion.aside
      className="sidebar"
      initial={{ x: -264 }}
      animate={{ x: 0 }}
      transition={{ duration: 0.5 }}
    >
      <div className="sidebar-brand">
        <motion.div
          className="brand-logo"
          whileHover={{ rotate: 360 }}
          transition={{ duration: 0.6 }}
        >
          U
        </motion.div>
        <div>
          <div className="brand-name">UAMS</div>
          <div className="brand-subtitle">Question Analysis</div>
        </div>
      </div>

      <nav className="sidebar-nav">
        {menuItems.map((item, index) => (
          <motion.button
            key={item.id}
            className={`nav-item ${currentPage === item.id ? 'active' : ''}`}
            onClick={() => setCurrentPage(item.id)}
            initial={{ opacity: 0, x: -20 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: index * 0.1 }}
            whileHover={{ x: 4 }}
          >
            <item.icon size={20} />
            <span>{item.label}</span>
          </motion.button>
        ))}
      </nav>

      <div className="sidebar-user-wrapper">
        <button
          className="sidebar-user"
          onClick={() => setShowUserMenu(!showUserMenu)}
        >
          <div className="user-avatar">
            {user.full_name.charAt(0)}
          </div>
          <div className="user-info">
            <div className="user-name">{user.full_name}</div>
            <div className="user-role">{user.role_name}</div>
          </div>
          <ChevronUp
            size={18}
            className={`user-menu-icon ${showUserMenu ? 'open' : ''}`}
          />
        </button>

        <AnimatePresence>
          {showUserMenu && (
            <motion.div
              className="user-dropdown"
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: 10 }}
              transition={{ duration: 0.2 }}
            >
              <button
                className="user-dropdown-item"
                onClick={() => {
                  setCurrentPage('profile')
                  setShowUserMenu(false)
                }}
              >
                <User size={16} />
                <span>{t('profile', language)}</span>
              </button>
              <button
                className="user-dropdown-item"
                onClick={() => {
                  setCurrentPage('settings')
                  setShowUserMenu(false)
                }}
              >
                <Settings size={16} />
                <span>{t('settings', language)}</span>
              </button>
              <div className="user-dropdown-divider"></div>
              <button
                className="user-dropdown-item danger"
                onClick={handleLogout}
              >
                <LogOut size={16} />
                <span>{t('logout', language)}</span>
              </button>
            </motion.div>
          )}
        </AnimatePresence>
      </div>
    </motion.aside>
  )
}

export default Sidebar
