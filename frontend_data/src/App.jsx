import React, { useState, useEffect } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import Login from './pages/Login'
import Dashboard from './pages/Dashboard'
import Questions from './pages/Questions'
import Import from './pages/Import'
import Settings from './pages/Settings'
import Users from './pages/Users'
import Audit from './pages/Audit'
import Sidebar from './components/Sidebar'
import TopNav from './components/TopNav'
import './App.css'

function App() {
  const validPages = ['dashboard', 'questions', 'import', 'settings', 'users', 'audit']
  const [currentPage, setCurrentPage] = useState(() => {
    const saved = localStorage.getItem('currentPage')
    return validPages.includes(saved) ? saved : 'dashboard'
  })
  const [searchTarget, setSearchTarget] = useState(null)
  const [user, setUser] = useState(null)
  const [darkMode, setDarkMode] = useState(() => {
    return localStorage.getItem('darkMode') === 'true'
  })
  const [language, setLanguage] = useState(() => {
    return localStorage.getItem('language') || 'en'
  })
  const [sidebarMinimal, setSidebarMinimal] = useState(() => {
    return localStorage.getItem('sidebarMinimal') === 'true'
  })

  useEffect(() => {
    if (window.USER_DATA) {
      setUser(window.USER_DATA)
    }
  }, [])

  useEffect(() => {
    localStorage.setItem('currentPage', currentPage)
  }, [currentPage])

  useEffect(() => {
    localStorage.setItem('darkMode', darkMode)
    if (darkMode) {
      document.body.classList.add('dark-mode')
    } else {
      document.body.classList.remove('dark-mode')
    }
  }, [darkMode])

  useEffect(() => {
    localStorage.setItem('language', language)
    document.documentElement.lang = language
  }, [language])

  const handleLogin = async (loginData) => {
    const response = await fetch('login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(loginData)
    })
    const data = await response.json()
    if (data.success) {
      setUser(data.user)
      setCurrentPage('dashboard')
    }
  }

  const handleLogout = async () => {
    await fetch('logout.php')
    setUser(null)
    window.location.href = 'login.php'
  }

  if (!user) {
    return <Login onLogin={handleLogin} />
  }

  return (
    <div className="app-shell">
      <Sidebar currentPage={currentPage} setCurrentPage={setCurrentPage} user={user} language={language} isMinimal={sidebarMinimal} setIsMinimal={setSidebarMinimal} />
      <div className="main-content" style={{ marginLeft: sidebarMinimal ? '72px' : '264px', transition: 'margin-left 0.3s cubic-bezier(0.4,0,0.2,1)' }}>
        <TopNav user={user} onLogout={handleLogout} darkMode={darkMode} setDarkMode={setDarkMode} language={language} setLanguage={setLanguage} setCurrentPage={setCurrentPage} setSearchTarget={setSearchTarget} />
        <AnimatePresence mode="wait">
          <motion.div
            key={currentPage}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -20 }}
            transition={{ duration: 0.3 }}
          >
            {currentPage === 'dashboard' && <Dashboard language={language} />}
            {currentPage === 'questions' && <Questions language={language} searchTarget={searchTarget} clearSearchTarget={() => setSearchTarget(null)} />}
            {currentPage === 'import' && <Import language={language} />}
            {currentPage === 'settings' && <Settings language={language} setLanguage={setLanguage} darkMode={darkMode} setDarkMode={setDarkMode} />}
            {currentPage === 'users' && <Users language={language} />}
            {currentPage === 'audit' && <Audit language={language} />}
          </motion.div>
        </AnimatePresence>
      </div>
    </div>
  )
}

export default App
