import React, { useState } from 'react'
import { motion } from 'framer-motion'
import { Moon, Globe, Bell, Mail, Save, Download, Trash2, Info, Settings as SettingsIcon } from 'lucide-react'
import { t } from '../utils/translations'
import './Settings.css'

const Settings = ({ language = 'en', setLanguage, darkMode, setDarkMode }) => {
  const [activeTab, setActiveTab] = useState('app')
  const [inAppNotifications, setInAppNotifications] = useState(true)
  const [emailNotifications, setEmailNotifications] = useState(true)
  const [autoSave, setAutoSave] = useState(true)

  const handleSaveSettings = () => {}

  const handleExportData = () => {}

  const handleClearCache = () => {}

  return (
    <div className="settings-content">
      <motion.div
        initial={{ opacity: 0, y: -20 }}
        animate={{ opacity: 1, y: 0 }}
      >
        <h2 className="page-title">{t('settings', language)}</h2>
        <p className="page-subtitle">{t('manageNotifications', language)}</p>
      </motion.div>

      <div className="settings-tabs">
        <button
          className={`settings-tab ${activeTab === 'app' ? 'active' : ''}`}
          onClick={() => setActiveTab('app')}
        >
          <SettingsIcon size={18} />
          {t('applicationSettings', language)}
        </button>
        <button
          className={`settings-tab ${activeTab === 'analytics' ? 'active' : ''}`}
          onClick={() => setActiveTab('analytics')}
        >
          <SettingsIcon size={18} />
          {t('analyticsRules', language)}
        </button>
      </div>

      {activeTab === 'app' && (
        <div className="settings-tab-content">

      <div className="settings-grid">
        <motion.div
          className="settings-section"
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.1 }}
        >
          <div className="section-header">
            <Moon size={20} />
            <div>
              <h3 className="section-title">{t('appearance', language)}</h3>
              <p className="section-description">{t('customizeAppearance', language)}</p>
            </div>
          </div>
          <div className="setting-item">
            <div className="setting-info">
              <label className="setting-label">{t('darkTheme', language)}</label>
            </div>
            <label className="toggle-switch">
              <input
                type="checkbox"
                checked={darkMode}
                onChange={(e) => setDarkMode(e.target.checked)}
              />
              <span className="toggle-slider"></span>
            </label>
          </div>
        </motion.div>

        <motion.div
          className="settings-section"
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.2 }}
        >
          <div className="section-header">
            <Globe size={20} />
            <div>
              <h3 className="section-title">{t('language', language)}</h3>
              <p className="section-description">{t('chooseLanguage', language)}</p>
            </div>
          </div>
          <div className="setting-item">
            <select
              className="language-select"
              value={language}
              onChange={(e) => setLanguage(e.target.value)}
            >
              <option value="en">English</option>
              <option value="ru">Русский</option>
              <option value="kz">Қазақша</option>
            </select>
          </div>
        </motion.div>

        <motion.div
          className="settings-section"
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.3 }}
        >
          <div className="section-header">
            <Bell size={20} />
            <div>
              <h3 className="section-title">{t('notifications', language)}</h3>
              <p className="section-description">{t('manageNotifications', language)}</p>
            </div>
          </div>
          <div className="setting-item">
            <div className="setting-info">
              <label className="setting-label">{t('inAppNotifications', language)}</label>
            </div>
            <label className="toggle-switch">
              <input
                type="checkbox"
                checked={inAppNotifications}
                onChange={(e) => setInAppNotifications(e.target.checked)}
              />
              <span className="toggle-slider"></span>
            </label>
          </div>
          <div className="setting-item">
            <div className="setting-info">
              <label className="setting-label">{t('emailNotifications', language)}</label>
            </div>
            <label className="toggle-switch">
              <input
                type="checkbox"
                checked={emailNotifications}
                onChange={(e) => setEmailNotifications(e.target.checked)}
              />
              <span className="toggle-slider"></span>
            </label>
          </div>
        </motion.div>

        <motion.div
          className="settings-section"
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.4 }}
        >
          <div className="section-header">
            <Save size={20} />
            <div>
              <h3 className="section-title">{t('dataPrivacy', language)}</h3>
              <p className="section-description">{t('manageData', language)}</p>
            </div>
          </div>
          <div className="setting-item">
            <div className="setting-info">
              <label className="setting-label">{t('autoSave', language)}</label>
            </div>
            <label className="toggle-switch">
              <input
                type="checkbox"
                checked={autoSave}
                onChange={(e) => setAutoSave(e.target.checked)}
              />
              <span className="toggle-slider"></span>
            </label>
          </div>
          <div className="setting-item">
            <button className="btn-secondary" onClick={handleExportData}>
              <Download size={16} />
              {t('exportMyData', language)}
            </button>
            <p className="setting-hint">{t('downloadData', language)}</p>
          </div>
        </motion.div>

        <motion.div
          className="settings-section"
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.5 }}
        >
          <div className="section-header">
            <Info size={20} />
            <div>
              <h3 className="section-title">{t('system', language)}</h3>
              <p className="section-description">{t('systemInfo', language)}</p>
            </div>
          </div>
          <div className="system-info">
            <div className="info-row">
              <span className="info-label">{t('version', language)}</span>
              <span className="info-value">2.1.0</span>
            </div>
            <div className="info-row">
              <span className="info-label">{t('lastUpdated', language)}</span>
              <span className="info-value">March 15, 2026</span>
            </div>
          </div>
          <div className="setting-item">
            <button className="btn-danger" onClick={handleClearCache}>
              <Trash2 size={16} />
              {t('clearCache', language)}
            </button>
          </div>
        </motion.div>
      </div>

      <motion.div
        className="settings-actions"
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.6 }}
      >
        <button className="btn-primary" onClick={handleSaveSettings}>
          <Save size={18} />
          {t('saveChanges', language)}
        </button>
      </motion.div>
        </div>
      )}

      {activeTab === 'analytics' && (
        <motion.div
          className="analytics-settings-frame"
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
        >
          <iframe
            src="/diplom_project/UAMS/ai_analytics/settings.php"
            loading="lazy"
            style={{
              width: '100%',
              height: 'calc(100vh - 280px)',
              border: 'none',
              borderRadius: '12px',
              background: 'white'
            }}
            title="Analytics Settings"
          />
        </motion.div>
      )}
    </div>
  )
}

export default Settings

