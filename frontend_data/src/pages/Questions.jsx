import React, { useRef, useEffect } from 'react'
import { motion } from 'framer-motion'
import './Questions.css'

const BASE = '/uams/ai_analytics/index.php'

const Questions = ({ language = 'en' }) => {
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
    <div className="questions-content">
      <motion.div
        className="ai-analytics-frame"
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.2 }}
      >
        <iframe
          ref={iframeRef}
          src={`${BASE}?lang=${language}`}
          style={{ width: '100%', height: 'calc(100vh - 140px)', border: 'none' }}
          loading="lazy"
          title="AI Analytics Module"
        />
      </motion.div>
    </div>
  )
}

export default Questions
