import React, { useState } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { Search, Moon, Sun, LogOut, Globe } from 'lucide-react'
import { t } from '../utils/translations'
import './TopNav.css'

const TopNav = ({ user, onLogout, darkMode, setDarkMode, language = 'en', setLanguage }) => {
  const [showLanguages, setShowLanguages] = useState(false)

  const languages = [
    { code: 'en', name: 'English', flag: '🇬🇧' },
    { code: 'ru', name: 'Русский', flag: '🇷🇺' },
    { code: 'kz', name: 'Қазақша', flag: '🇰🇿' }
  ]

  const handleLanguageChange = (langCode) => {
    setLanguage(langCode)
    setShowLanguages(false)
  }

  return (
    <motion.header
      className="top-nav"
      initial={{ y: -88 }}
      animate={{ y: 0 }}
      transition={{ duration: 0.5 }}
    >
      <div className="nav-title">
        <h1>{t('dashboard', language)}</h1>
        <p>{t('welcomeBack', language)}, {user.full_name}</p>
      </div>

      <div className="nav-search">
        <Search size={18} />
        <input type="text" placeholder={t('search', language)} />
        <kbd>⌘K</kbd>
      </div>

      <div className="nav-actions">
        <div className="notification-wrapper">
          <motion.button
            className="nav-icon-with-label"
            whileHover={{ scale: 1.05 }}
            whileTap={{ scale: 0.95 }}
            onClick={() => setShowLanguages(!showLanguages)}
          >
            <Globe size={20} />
            <span className="icon-label">{language.toUpperCase()}</span>
          </motion.button>

          <AnimatePresence>
            {showLanguages && (
              <motion.div
                className="language-dropdown"
                initial={{ opacity: 0, y: -10, scale: 0.95 }}
                animate={{ opacity: 1, y: 0, scale: 1 }}
                exit={{ opacity: 0, y: -10, scale: 0.95 }}
                transition={{ duration: 0.2 }}
              >
                <div className="language-header">
                  <h3>Select Language</h3>
                </div>
                <div className="language-list">
                  {languages.map((lang) => (
                    <button
                      key={lang.code}
                      className={`language-item ${language === lang.code ? 'active' : ''}`}
                      onClick={() => handleLanguageChange(lang.code)}
                    >
                      <span className="language-flag">{lang.flag}</span>
                      <span className="language-name">{lang.name}</span>
                      {language === lang.code && (
                        <span className="language-check">✓</span>
                      )}
                    </button>
                  ))}
                </div>
              </motion.div>
            )}
          </AnimatePresence>
        </div>

        <motion.button
          className="nav-icon-with-label"
          whileHover={{ scale: 1.05 }}
          whileTap={{ scale: 0.95 }}
          onClick={() => setDarkMode(!darkMode)}
        >
          {darkMode ? <Sun size={20} /> : <Moon size={20} />}
          <span className="icon-label">{darkMode ? t('lightMode', language) : t('darkMode', language)}</span>
        </motion.button>

        <motion.button
          className="logout-btn"
          onClick={onLogout}
          whileHover={{ scale: 1.05 }}
          whileTap={{ scale: 0.95 }}
        >
          <LogOut size={18} />
          {t('logout', language)}
        </motion.button>
      </div>
    </motion.header>
  )
}

export default TopNav
