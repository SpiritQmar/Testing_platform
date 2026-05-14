import React, { useRef, useEffect } from 'react'
import { motion } from 'framer-motion'
import './Questions.css'

const BASE = '/uams/ai_analytics/index.php'

const Questions = ({ language = 'en', searchTarget }) => {
  const iframeRef = useRef(null)
  const lastLang = useRef(language)
  const lastTargetKey = useRef(null)

  const buildUrl = (lang, target) => {
    const params = new URLSearchParams()
    params.set('lang', lang)
    if (target) {
      if (target.type === 'section_with_highlight' && target.section) {
        params.set('section', target.section)
        params.set('highlight_type', target.highlight_type)
        params.set('highlight_id', String(target.highlight_id))
      } else if (target.type === 'section' && target.section) {
        params.set('section', target.section)
      } else if (target.id) {
        let section = 'quality'
        if (target.type === 'topic')   section = 'semantic'
        if (target.type === 'student') section = 'students'
        params.set('section', section)
        params.set('highlight_type', target.type)
        params.set('highlight_id', String(target.id))
      }
    }
    return `${BASE}?${params.toString()}`
  }

  const initialSrc = useRef(buildUrl(language, searchTarget)).current
  if (lastTargetKey.current === null && searchTarget) {
    lastTargetKey.current = JSON.stringify(searchTarget)
  }

  useEffect(() => {
    if (!iframeRef.current) return
    if (lastLang.current === language) return
    lastLang.current = language
    iframeRef.current.src = buildUrl(language, null)
  }, [language])

  useEffect(() => {
    if (!iframeRef.current || !searchTarget) return
    const key = JSON.stringify(searchTarget)
    if (lastTargetKey.current === key) return
    lastTargetKey.current = key
    iframeRef.current.src = buildUrl(language, searchTarget)
  }, [searchTarget])

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
          src={initialSrc}
          style={{ width: '100%', height: 'calc(100vh - 140px)', border: 'none' }}
          loading="lazy"
          title="AI Analytics Module"
        />
      </motion.div>
    </div>
  )
}

export default Questions
