import React, { useState, useEffect, useRef } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { Search, Moon, Sun, LogOut, Globe, Sparkles, FileQuestion, BookOpen, Compass, Layers, Upload, Hash, User, ClipboardCheck, GitBranch, ListChecks, MessageSquare } from 'lucide-react'
import { t } from '../utils/translations'
import './TopNav.css'

const NAV_ITEMS = [
  { type: 'nav', page: 'dashboard',                     label: 'Обзор',                              keywords: 'обзор dashboard главная статистика overview' },
  { type: 'nav', page: 'import',                        label: 'Импорт',                             keywords: 'импорт загрузка данных файл csv import' },
  { type: 'nav', page: 'questions',                     label: 'ИИ аналитика',                       keywords: 'ии ai аналитика вопросы analytics questions' },
  { type: 'nav', page: 'settings',                      label: 'Настройки',                          keywords: 'настройки конфигурация коэффициенты settings правила' },
  { type: 'nav', page: 'users',                         label: 'Пользователи',                       keywords: 'пользователи users роли' },
  { type: 'nav', page: 'audit',                         label: 'Аудит',                              keywords: 'аудит audit журнал лог' },
  { type: 'nav', page: 'questions', section: 'validation',    label: 'Валидация',                  keywords: 'валидация validation проверка соответствия' },
  { type: 'nav', page: 'questions', section: 'quality',       label: 'Анализ качества',           keywords: 'качество quality сложность вопросов лёгкие сложные' },
  { type: 'nav', page: 'questions', section: 'correlation',   label: 'Корреляции',                keywords: 'корреляция correlation связь баллы' },
  { type: 'nav', page: 'questions', section: 'students',      label: 'Студенты',                  keywords: 'студенты students риск паттерны' },
  { type: 'nav', page: 'questions', section: 'semantic',      label: 'Семантика',                 keywords: 'семантика semantic эмбеддинги embeddings силлабус' },
  { type: 'nav', page: 'questions', section: 'criteria',      label: 'Анализ критериев',          keywords: 'критерии criteria оценочный лист рубрики' },
  { type: 'nav', page: 'questions', section: 'answers',       label: 'Ответы студентов',          keywords: 'ответы answers плагиат язык students' },
  { type: 'nav', page: 'questions', section: 'syllabus-link', label: 'Привязка силлабуса',        keywords: 'силлабус syllabus привязка связь link' },
]

function filterNav(query) {
  const q = query.trim().toLowerCase()
  if (!q) return []
  return NAV_ITEMS.filter(item => {
    const hay = (item.label + ' ' + item.keywords).toLowerCase()
    return hay.includes(q)
  }).slice(0, 6)
}

const TopNav = ({ user, onLogout, darkMode, setDarkMode, language = 'en', setLanguage, setCurrentPage, setSearchTarget }) => {
  const sectionLabels = {
    'quality':       'Качество',
    'validation':    'Валидация',
    'correlation':   'Корреляция',
    'semantic':      'Семантика',
    'students':      'Студенты',
    'criteria':      'Критерии',
    'answers':       'Ответы',
    'syllabus-link': 'Силлабус',
    'import':        'Импорт',
  }

  const handleSectionClick = (r, section) => {
    if (setSearchTarget) {
      setSearchTarget({
        type: 'section_with_highlight',
        section: section,
        highlight_type: r.entity_type,
        highlight_id: r.entity_id,
        _ts: Date.now(),
      })
    }
    if (setCurrentPage) setCurrentPage('questions')
    setSearchOpen(false)
    setSearchQuery('')
  }

  const handleResultClick = (r) => {
    const ts = Date.now()
    if (r.type === 'nav') {
      if (r.section && setSearchTarget) {
        setSearchTarget({ type: 'section', section: r.section, _ts: ts })
      } else if (setSearchTarget) {
        setSearchTarget(null)
      }
      if (setCurrentPage) setCurrentPage(r.page)
    } else if (r.entity_type === 'discipline') {
      if (setSearchTarget) setSearchTarget({ type: 'section', section: 'syllabus-link', _ts: ts })
      if (setCurrentPage) setCurrentPage('questions')
    } else if (r.entity_type === 'import') {
      if (setSearchTarget) setSearchTarget({ type: 'section', section: 'import', _ts: ts })
      if (setCurrentPage) setCurrentPage('questions')
    } else if (r.entity_type === 'student') {
      if (setSearchTarget) setSearchTarget({ type: 'student', id: r.entity_id, _ts: ts })
      if (setCurrentPage) setCurrentPage('questions')
    } else if (r.entity_type === 'exam') {
      if (setSearchTarget) setSearchTarget({ type: 'section', section: 'correlation', _ts: ts })
      if (setCurrentPage) setCurrentPage('questions')
    } else if (r.entity_type === 'rule') {
      if (setCurrentPage) setCurrentPage('settings')
    } else if (r.entity_type === 'criteria') {
      if (setSearchTarget) setSearchTarget({ type: 'section', section: 'criteria', _ts: ts })
      if (setCurrentPage) setCurrentPage('questions')
    } else if (r.entity_type === 'answer') {
      if (setSearchTarget) setSearchTarget({ type: 'section', section: 'answers', _ts: ts })
      if (setCurrentPage) setCurrentPage('questions')
    } else {
      if (setSearchTarget) setSearchTarget({ type: r.entity_type, id: r.entity_id, _ts: ts })
      if (setCurrentPage) setCurrentPage('questions')
    }
    setSearchOpen(false)
    setSearchQuery('')
  }

  const iconForType = (t) => {
    if (t === 'question')   return <FileQuestion size={14} style={{ color: '#3b82f6' }} />
    if (t === 'topic')      return <BookOpen size={14} style={{ color: '#10b981' }} />
    if (t === 'discipline') return <Layers size={14} style={{ color: '#a855f7' }} />
    if (t === 'import')     return <Upload size={14} style={{ color: '#f59e0b' }} />
    if (t === 'student')    return <User size={14} style={{ color: '#06b6d4' }} />
    if (t === 'exam')       return <ClipboardCheck size={14} style={{ color: '#ec4899' }} />
    if (t === 'rule')       return <GitBranch size={14} style={{ color: '#84cc16' }} />
    if (t === 'criteria')   return <ListChecks size={14} style={{ color: '#0891b2' }} />
    if (t === 'answer')     return <MessageSquare size={14} style={{ color: '#7c3aed' }} />
    return <Hash size={14} style={{ color: '#6b7280' }} />
  }

  const labelForType = (t) => {
    if (t === 'question')   return 'Вопрос'
    if (t === 'topic')      return 'Тема'
    if (t === 'discipline') return 'Дисциплина'
    if (t === 'import')     return 'Импорт'
    if (t === 'student')    return 'Студент'
    if (t === 'exam')       return 'Экзамен'
    if (t === 'rule')       return 'Правило'
    if (t === 'criteria')   return 'Критерий'
    if (t === 'answer')     return 'Ответ'
    return t
  }

  const [showLanguages, setShowLanguages] = useState(false)
  const [searchQuery, setSearchQuery] = useState('')
  const [searchResults, setSearchResults] = useState(null)
  const [navMatches, setNavMatches] = useState([])
  const [searchOpen, setSearchOpen] = useState(false)
  const [searchLoading, setSearchLoading] = useState(false)
  const [searchMode, setSearchMode] = useState('')
  const [indexSize, setIndexSize] = useState(null)
  const debounceRef = useRef(null)
  const wrapperRef = useRef(null)

  const languages = [
    { code: 'en', name: 'English', flag: '🇬🇧' },
    { code: 'ru', name: 'Русский', flag: '🇷🇺' },
    { code: 'kz', name: 'Қазақша', flag: '🇰🇿' }
  ]

  const handleLanguageChange = (langCode) => {
    setLanguage(langCode)
    setShowLanguages(false)
  }

  useEffect(() => {
    if (debounceRef.current) clearTimeout(debounceRef.current)
    const trimmed = (searchQuery || '').trim()
    const isNumeric = /^\d+$/.test(trimmed)
    if (!trimmed || (!isNumeric && trimmed.length < 2)) {
      setSearchResults(null)
      setNavMatches([])
      setSearchLoading(false)
      return
    }
    setNavMatches(filterNav(searchQuery))
    setSearchLoading(true)
    debounceRef.current = setTimeout(async () => {
      try {
        const r = await fetch('/uams/ai_analytics/api/search.php?q=' + encodeURIComponent(searchQuery) + '&top_k=8&_=' + Date.now(), { cache: 'no-store' })
        const d = await r.json()
        if (d.success) {
          setSearchResults(d.results || [])
          setSearchMode(d.mode || '')
          setIndexSize(typeof d.index_size === 'number' ? d.index_size : null)
        } else {
          setSearchResults([])
          setSearchMode('error')
        }
      } catch (err) {
        setSearchResults([])
        setSearchMode('error')
      } finally {
        setSearchLoading(false)
      }
    }, 300)
    return () => debounceRef.current && clearTimeout(debounceRef.current)
  }, [searchQuery])

  useEffect(() => {
    const onClick = (e) => {
      if (wrapperRef.current && !wrapperRef.current.contains(e.target)) setSearchOpen(false)
    }
    document.addEventListener('mousedown', onClick)
    return () => document.removeEventListener('mousedown', onClick)
  }, [])

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

      <div className="nav-search" ref={wrapperRef} style={{ position: 'relative' }}>
        <Search size={18} />
        <input
          type="text"
          placeholder={t('search', language)}
          value={searchQuery}
          onChange={(e) => { setSearchQuery(e.target.value); setSearchOpen(true) }}
          onFocus={() => setSearchOpen(true)}
        />
        {searchMode === 'semantic' && <Sparkles size={14} style={{ color: '#a855f7', marginRight: 4 }} />}
        <kbd>⌘K</kbd>

        <AnimatePresence>
          {searchOpen && (searchQuery.trim().length >= 2 || /^\d+$/.test(searchQuery.trim())) && (
            <motion.div
              initial={{ opacity: 0, y: -8 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -8 }}
              transition={{ duration: 0.15 }}
              style={{
                position: 'absolute', top: 'calc(100% + 6px)', left: 0, right: 0,
                background: '#fff', borderRadius: 12, boxShadow: '0 8px 32px rgba(0,0,0,0.12)',
                maxHeight: 420, overflowY: 'auto', zIndex: 100, border: '1px solid #e5e7eb'
              }}
            >
              {navMatches.length > 0 && (
                <>
                  <div style={{ padding: '8px 14px', background: '#f9fafb', fontSize: 11, color: '#6b7280', textTransform: 'uppercase', letterSpacing: 0.5, borderBottom: '1px solid #f3f4f6' }}>
                    <Compass size={12} style={{ display: 'inline', verticalAlign: 'middle', marginRight: 4 }} />
                    Навигация
                  </div>
                  {navMatches.map((nav, idx) => (
                    <div
                      key={'nav-' + idx}
                      style={{
                        padding: '9px 14px',
                        borderBottom: '1px solid #f3f4f6',
                        cursor: 'pointer',
                        display: 'flex',
                        alignItems: 'center',
                        gap: 8
                      }}
                      onMouseEnter={(e) => e.currentTarget.style.background = '#eff6ff'}
                      onMouseLeave={(e) => e.currentTarget.style.background = '#fff'}
                      onClick={() => handleResultClick(nav)}
                    >
                      <Compass size={14} style={{ color: '#3b82f6' }} />
                      <span style={{ fontSize: 13, color: '#1f2937', fontWeight: 500 }}>
                        {nav.label}
                      </span>
                      {nav.section && (
                        <span style={{ fontSize: 11, color: '#9ca3af', marginLeft: 'auto' }}>
                          ИИ аналитика
                        </span>
                      )}
                    </div>
                  ))}
                </>
              )}
              {indexSize === 0 && searchMode !== 'id_lookup' && (
                <div style={{ padding: '10px 14px', background: '#fef3c7', borderBottom: '1px solid #fde68a', fontSize: 12, color: '#92400e', display: 'flex', alignItems: 'center', gap: 8 }}>
                  <span>⚠️</span>
                  <span style={{ flex: 1 }}>
                    Семантический индекс не построен (0 элементов). Сейчас работает поиск по ключевым словам.
                  </span>
                  <button
                    onClick={(e) => {
                      e.stopPropagation()
                      if (setCurrentPage) setCurrentPage('settings')
                      setSearchOpen(false)
                      setSearchQuery('')
                    }}
                    style={{ padding: '3px 9px', fontSize: 11, border: '1px solid #d97706', background: '#f59e0b', color: '#fff', borderRadius: 5, cursor: 'pointer' }}
                  >
                    Построить
                  </button>
                </div>
              )}
              <div style={{ padding: '10px 14px', borderBottom: '1px solid #f3f4f6', fontSize: 12, color: '#6b7280', display: 'flex', justifyContent: 'space-between' }}>
                <span>
                  {searchMode === 'semantic' && <><Sparkles size={12} style={{ display: 'inline', verticalAlign: 'middle' }} /> AI поиск (семантический)</>}
                  {searchMode === 'keyword' && '🔍 Поиск по ключевым словам'}
                  {searchMode === 'id_lookup' && '🔢 Поиск по ID'}
                  {searchMode === 'error' && '⚠️ Ошибка'}
                </span>
                {searchLoading && <span>загрузка…</span>}
              </div>

              {!searchLoading && searchResults && searchResults.length === 0 && navMatches.length === 0 && (
                <div style={{ padding: 24, textAlign: 'center', color: '#9ca3af', fontSize: 13 }}>
                  Ничего не найдено
                </div>
              )}

              {searchResults && searchResults.map((r, i) => (
                <div
                  key={r.entity_type + ':' + r.entity_id}
                  style={{
                    padding: '10px 14px',
                    borderBottom: i === searchResults.length - 1 ? 'none' : '1px solid #f3f4f6',
                    cursor: 'pointer'
                  }}
                  onMouseEnter={(e) => e.currentTarget.style.background = '#f9fafb'}
                  onMouseLeave={(e) => e.currentTarget.style.background = '#fff'}
                  onClick={() => handleResultClick(r)}
                >
                  <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 4 }}>
                    {iconForType(r.entity_type)}
                    <span style={{ fontSize: 11, color: '#6b7280', textTransform: 'uppercase', letterSpacing: 0.5 }}>
                      {labelForType(r.entity_type)} #{r.entity_id}
                    </span>
                    {r.score !== null && (
                      <span style={{ marginLeft: 'auto', fontSize: 11, padding: '2px 6px', background: '#f3e8ff', color: '#7e22ce', borderRadius: 4 }}>
                        {(r.score * 100).toFixed(0)}%
                      </span>
                    )}
                  </div>
                  <div style={{ fontSize: 13, color: '#1f2937', lineHeight: 1.4 }}>
                    {r.content && r.content.length > 120 ? r.content.substring(0, 120) + '…' : r.content}
                  </div>
                  {r.meta && (r.meta.discipline || r.meta.topic) && (
                    <div style={{ fontSize: 11, color: '#9ca3af', marginTop: 4 }}>
                      {r.meta.discipline}{r.meta.topic ? ' / ' + r.meta.topic : ''}
                    </div>
                  )}
                  {r.meta && r.meta.type && (
                    <div style={{ fontSize: 11, color: '#9ca3af', marginTop: 4 }}>
                      {r.meta.type} · {r.meta.rows} строк
                    </div>
                  )}
                  {r.meta && r.meta.avg !== undefined && (
                    <div style={{ fontSize: 11, color: '#9ca3af', marginTop: 4 }}>
                      Ср: {r.meta.avg} · Мин: {r.meta.min} · Макс: {r.meta.max} · {r.meta.n_items} ответов
                    </div>
                  )}
                  {r.meta && r.meta.students !== undefined && (
                    <div style={{ fontSize: 11, color: '#9ca3af', marginTop: 4 }}>
                      {r.meta.students} студентов · {r.meta.attempts} попыток
                    </div>
                  )}
                  {r.meta && r.meta.active !== undefined && (
                    <div style={{ fontSize: 11, color: '#9ca3af', marginTop: 4 }}>
                      {r.meta.active ? '✓ Активно' : '○ Выключено'}
                    </div>
                  )}
                  {r.meta && r.meta.weight !== undefined && (
                    <div style={{ fontSize: 11, color: '#9ca3af', marginTop: 4 }}>
                      {r.meta.name_en} · вес {r.meta.weight}%
                    </div>
                  )}
                  {r.meta && r.meta.work_id !== undefined && (
                    <div style={{ fontSize: 11, color: '#9ca3af', marginTop: 4 }}>
                      Работа {r.meta.work_id} · {r.meta.lang} · балл {r.meta.score}
                      {r.meta.penalty > 0 && <span style={{ color: '#ef4444' }}> · штраф {r.meta.penalty}</span>}
                    </div>
                  )}
                  {r.meta && (r.meta.validation_status || r.meta.alignment_level || r.meta.avg_score !== null) && (
                    <div style={{ fontSize: 11, marginTop: 6, display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                      {r.meta.avg_score !== null && r.meta.avg_score !== undefined && (
                        <span style={{ padding: '2px 7px', borderRadius: 10, background: '#eff6ff', color: '#1d4ed8' }}>
                          ср: {r.meta.avg_score} · {r.meta.attempts} попыток
                        </span>
                      )}
                      {r.meta.validation_status && (
                        <span style={{ padding: '2px 7px', borderRadius: 10, background: r.meta.validation_status === 'ok' ? '#d1fae5' : '#fef3c7', color: r.meta.validation_status === 'ok' ? '#065f46' : '#92400e' }}>
                          валидация: {r.meta.validation_status}
                        </span>
                      )}
                      {r.meta.alignment_level && (
                        <span style={{ padding: '2px 7px', borderRadius: 10, background: r.meta.alignment_level === 'high' ? '#d1fae5' : r.meta.alignment_level === 'medium' ? '#fef3c7' : '#fee2e2', color: r.meta.alignment_level === 'high' ? '#065f46' : r.meta.alignment_level === 'medium' ? '#92400e' : '#991b1b' }}>
                          сходство: {r.meta.alignment_level}
                        </span>
                      )}
                    </div>
                  )}
                  {r.meta && Array.isArray(r.meta.sections) && r.meta.sections.length > 0 && (
                    <div style={{ fontSize: 11, marginTop: 6, display: 'flex', gap: 4, flexWrap: 'wrap' }} onClick={(e) => e.stopPropagation()}>
                      <span style={{ color: '#9ca3af', alignSelf: 'center' }}>открыть в:</span>
                      {r.meta.sections.map(sec => (
                        <button
                          key={sec}
                          onClick={(e) => { e.stopPropagation(); handleSectionClick(r, sec) }}
                          style={{ padding: '3px 9px', fontSize: 11, border: '1px solid #d1d5db', background: '#fff', borderRadius: 6, cursor: 'pointer', color: '#374151' }}
                          onMouseEnter={(e) => { e.currentTarget.style.background = '#3b82f6'; e.currentTarget.style.color = '#fff'; e.currentTarget.style.borderColor = '#3b82f6' }}
                          onMouseLeave={(e) => { e.currentTarget.style.background = '#fff'; e.currentTarget.style.color = '#374151'; e.currentTarget.style.borderColor = '#d1d5db' }}
                        >
                          {sectionLabels[sec] || sec}
                        </button>
                      ))}
                    </div>
                  )}
                </div>
              ))}
            </motion.div>
          )}
        </AnimatePresence>
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
