import React, { useRef, useEffect } from 'react'
import { motion } from 'framer-motion'
import { t } from '../utils/translations'
import './Import.css'

const BASE = '/uams/backend/import/import.php'

const Import = ({ language = 'en', setCurrentPage }) => {
  const iframeRef = useRef(null)
  const currentLang = useRef(language)

  useEffect(() => {
    if (currentLang.current === language) return
    currentLang.current = language
    const t = setTimeout(() => {
      if (iframeRef.current) {
        iframeRef.current.src = `${BASE}?lang=${language}`
      }
    }, 300)
    return () => clearTimeout(t)
  }, [language])

  return (
    <div className="import-content">
      <motion.h2
        className="page-title"
        initial={{ opacity: 0, y: -20 }}
        animate={{ opacity: 1, y: 0 }}
      >
        {t('importData', language)}
      </motion.h2>
      <p className="page-subtitle">{t('importDataSubtitle', language)}</p>

      <motion.div
        className="import-frame"
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.2 }}
      >
        <iframe
          ref={iframeRef}
          src={`${BASE}?lang=${language}`}
          style={{
            width: '100%',
            height: 'calc(100vh - 200px)',
            border: 'none',
            borderRadius: '8px',
            boxShadow: '0 2px 8px rgba(0,0,0,0.1)'
          }}
          title="Import Module"
        />
      </motion.div>
    </div>
  )
}

export default Import
