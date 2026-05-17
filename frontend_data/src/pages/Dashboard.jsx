import React, { useState, useEffect, useRef } from 'react'
import { motion } from 'framer-motion'
import { TrendingUp, TrendingDown, FileQuestion, Users, ClipboardList, Award, Database, Cpu, SlidersHorizontal, Gauge, Network, Scan } from 'lucide-react'
import { t } from '../utils/translations'
import './Dashboard.css'

const Dashboard = ({ language = 'en' }) => {
  const [data, setData] = useState(null)
  const [charts, setCharts] = useState(null)
  const [loading, setLoading] = useState(true)
  const chartRefs = {
    quality: useRef(null),
    correlation: useRef(null),
    students: useRef(null),
    validation: useRef(null),
    semantic: useRef(null),
    criteria: useRef(null),
    answers: useRef(null)
  }
  const chartInstances = useRef({})

  useEffect(() => {
    Promise.all([
      fetch('/uams/ai_analytics/api/overview.php?v=' + Date.now()).then(res => res.json()),
      fetch('/uams/ai_analytics/api/charts.php?v=' + Date.now()).then(res => res.json())
    ])
      .then(([overviewData, chartsData]) => {
        setData(overviewData)
        setCharts(chartsData)
        setLoading(false)
      })
      .catch(() => {
        setLoading(false)
      })
  }, [])

  useEffect(() => {
    if (!charts || !charts.success) return

    Object.keys(chartInstances.current).forEach(key => {
      if (chartInstances.current[key]) {
        chartInstances.current[key].destroy()
      }
    })

    const createChart = (ref, data, type = 'doughnut') => {
      if (!ref.current || !data || !data.datasets || !data.datasets[0]) return null

      const ctx = ref.current.getContext('2d')

      const tooltipDefaults = {
        backgroundColor: '#0f172a',
        titleColor: '#f1f5f9',
        bodyColor: '#94a3b8',
        borderColor: '#1e293b',
        borderWidth: 1,
        padding: 12,
        cornerRadius: 10,
        titleFont: { size: 12, weight: '600', family: "'Inter', -apple-system, sans-serif" },
        bodyFont: { size: 12, family: "'Inter', -apple-system, sans-serif" },
        displayColors: true,
        boxWidth: 8,
        boxHeight: 8,
        usePointStyle: true,
        callbacks: {
          label: function(context) {
            const label = context.label || ''
            let value = 0
            if (type === 'doughnut' || type === 'pie') value = context.parsed
            else if (type === 'bar' || type === 'line') value = context.parsed.y
            else if (type === 'radar') value = context.parsed.r
            return ` ${label}: ${value}`
          }
        }
      }

      const options = {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 700, easing: 'easeInOutQuart' },
        plugins: {
          legend: {
            display: type === 'doughnut' || type === 'pie',
            position: 'bottom',
            labels: {
              padding: 20,
              usePointStyle: true,
              pointStyleWidth: 10,
              font: { size: 12, family: "'Inter', -apple-system, sans-serif", weight: '500' },
              color: '#64748b',
              boxWidth: 10,
              boxHeight: 10
            }
          },
          tooltip: tooltipDefaults
        }
      }

      if (type === 'doughnut' || type === 'pie') {
        options.cutout = type === 'doughnut' ? '72%' : '0%'
        data.datasets[0].hoverOffset = 10
        data.datasets[0].borderWidth = 3
        data.datasets[0].borderColor = '#ffffff'
        data.datasets[0].hoverBorderColor = '#ffffff'
      }

      if (type === 'bar') {
        options.scales = {
          y: {
            beginAtZero: true,
            grid: { color: 'rgba(226,232,240,0.7)', drawBorder: false },
            ticks: { color: '#94a3b8', font: { size: 11 } },
            border: { display: false }
          },
          x: {
            grid: { display: false },
            ticks: { color: '#64748b', font: { size: 11 } },
            border: { display: false }
          }
        }
        if (data.labels && data.labels.length <= 2) {
          options.indexAxis = 'y'
          options.scales = {
            x: { beginAtZero: true, grid: { color: 'rgba(226,232,240,0.7)' }, border: { display: false }, ticks: { color: '#94a3b8', font: { size: 11 } } },
            y: { grid: { display: false }, border: { display: false }, ticks: { color: '#64748b', font: { size: 11 } } }
          }
        }
        data.datasets[0].borderRadius = 8
        data.datasets[0].borderSkipped = false
      }

      if (type === 'line') {
        options.scales = {
          y: {
            beginAtZero: true,
            grid: { color: 'rgba(226,232,240,0.7)', drawBorder: false },
            ticks: { color: '#94a3b8', font: { size: 11 } },
            border: { display: false }
          },
          x: {
            grid: { display: false },
            ticks: { color: '#64748b', font: { size: 11 } },
            border: { display: false }
          }
        }
        data.datasets.forEach(ds => {
          const baseColor = Array.isArray(ds.backgroundColor) ? ds.backgroundColor[0] : (ds.backgroundColor || '#6366f1')
          ds.fill = true
          ds.tension = 0.45
          ds.borderColor = baseColor
          ds.borderWidth = 2.5
          ds.pointBackgroundColor = baseColor
          ds.pointBorderColor = '#fff'
          ds.pointBorderWidth = 2
          ds.pointRadius = 5
          ds.pointHoverRadius = 7
          const grad = ctx.createLinearGradient(0, 0, 0, 260)
          grad.addColorStop(0, baseColor + '50')
          grad.addColorStop(1, baseColor + '00')
          ds.backgroundColor = grad
        })
      }

      if (type === 'radar') {
        options.plugins.legend.display = false
        options.scales = {
          r: {
            beginAtZero: true,
            min: 0,
            max: 100,
            grid: { color: 'rgba(226,232,240,0.9)' },
            angleLines: { color: 'rgba(226,232,240,0.9)' },
            ticks: { color: '#94a3b8', font: { size: 10 }, backdropColor: 'transparent', stepSize: 25 },
            pointLabels: { color: '#64748b', font: { size: 11, weight: '500', family: "'Inter', sans-serif" } }
          }
        }
        data.datasets[0].borderWidth = 2
        data.datasets[0].borderColor = '#6366f1'
        data.datasets[0].backgroundColor = 'rgba(99,102,241,0.15)'
        data.datasets[0].pointBackgroundColor = '#6366f1'
        data.datasets[0].pointBorderColor = '#fff'
        data.datasets[0].pointBorderWidth = 2
        data.datasets[0].pointRadius = 5
        data.datasets[0].pointHoverRadius = 7
      }

      return new window.Chart(ctx, { type, data, options })
    }

    if (charts.quality) {
      chartInstances.current.quality = createChart(chartRefs.quality, charts.quality)
    }
    if (charts.correlation) {
      chartInstances.current.correlation = createChart(chartRefs.correlation, charts.correlation, 'doughnut')
    }
    if (charts.studentRisk) {
      chartInstances.current.students = createChart(chartRefs.students, charts.studentRisk)
    }
    if (charts.validation) {
      chartInstances.current.validation = createChart(chartRefs.validation, charts.validation)
    }
    if (charts.semantic) {
      chartInstances.current.semantic = createChart(chartRefs.semantic, charts.semantic, 'doughnut')
    }
    if (charts.criteria) {
      chartInstances.current.criteria = createChart(chartRefs.criteria, charts.criteria, 'radar')
    }
    if (charts.plagiarism) {
      chartInstances.current.answers = createChart(chartRefs.answers, charts.plagiarism, 'line')
    }

    return () => {
      Object.keys(chartInstances.current).forEach(key => {
        if (chartInstances.current[key]) {
          chartInstances.current[key].destroy()
        }
      })
    }
  }, [charts])

  if (loading) {
    return (
      <div className="dashboard-content">
        <div className="loading-spinner">{t('loading', language)}</div>
      </div>
    )
  }

  if (!data || !data.success) {
    return (
      <div className="dashboard-content">
        <div className="error-message">{t('errorLoadingData', language)}</div>
      </div>
    )
  }

  const metrics = [
    {
      icon: FileQuestion,
      label: t('questions', language),
      value: data.stats.questions,
      change: data.changes.questions,
      color: 'blue'
    },
    {
      icon: Users,
      label: t('students', language),
      value: data.stats.students,
      change: data.changes.students,
      color: 'green'
    },
    {
      icon: ClipboardList,
      label: t('attempts', language),
      value: data.stats.attempts,
      change: data.changes.attempts,
      color: 'purple'
    },
    {
      icon: Award,
      label: t('avgScore', language),
      value: data.stats.avg_score,
      change: data.changes.avg_score,
      color: 'orange'
    }
  ]

  return (
    <div className="dashboard-content">
      <motion.div
        initial={{ opacity: 0, y: -20 }}
        animate={{ opacity: 1, y: 0 }}
      >
        <h2 className="page-title">{t('overview', language)}</h2>
        <p className="page-subtitle">{t('statisticsAndAnalytics', language)}</p>
      </motion.div>

      <div className="metrics-grid">
        {metrics.map((metric, index) => (
          <motion.div
            key={metric.label}
            className={`metric-card metric-${metric.color}`}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: index * 0.1 }}
          >
            <div className="metric-icon">
              <metric.icon size={24} />
            </div>
            <div className="metric-content">
              <div className="metric-value">{metric.value.toLocaleString()}</div>
              <div className="metric-label">{metric.label}</div>
              <div className={`metric-change ${metric.change.isPositive ? 'positive' : 'negative'}`}>
                {metric.change.isPositive ? <TrendingUp size={16} /> : <TrendingDown size={16} />}
                <span>{metric.change.isPositive ? '+' : '-'}{metric.change.change}%</span>
              </div>
            </div>
          </motion.div>
        ))}
      </div>

      <motion.div
        className="additional-metrics"
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.3 }}
      >
        <div className="additional-metrics-grid">
          <div className="additional-metric-card">
            <div className="additional-metric-label">{t('totalDocuments', language)}</div>
            <div className="additional-metric-value">{data.additionalStats.total_documents.toLocaleString()}</div>
            <div className={`additional-metric-change ${data.additionalChanges.total_documents.isPositive ? 'positive' : 'negative'}`}>
              {data.additionalChanges.total_documents.isPositive ? <TrendingUp size={14} /> : <TrendingDown size={14} />}
              <span>{data.additionalChanges.total_documents.isPositive ? '+' : '-'}{data.additionalChanges.total_documents.change}%</span>
            </div>
          </div>
          <div className="additional-metric-card">
            <div className="additional-metric-label">{t('dataProcessed', language)}</div>
            <div className="additional-metric-value">{data.additionalStats.data_processed} MB</div>
            <div className={`additional-metric-change ${data.additionalChanges.data_processed.isPositive ? 'positive' : 'negative'}`}>
              {data.additionalChanges.data_processed.isPositive ? <TrendingUp size={14} /> : <TrendingDown size={14} />}
              <span>{data.additionalChanges.data_processed.isPositive ? '+' : '-'}{data.additionalChanges.data_processed.change}%</span>
            </div>
          </div>
          <div className="additional-metric-card">
            <div className="additional-metric-label">{t('activeQueries', language)}</div>
            <div className="additional-metric-value">{data.additionalStats.active_queries.toLocaleString()}</div>
            <div className={`additional-metric-change ${data.additionalChanges.active_queries.isPositive ? 'positive' : 'negative'}`}>
              {data.additionalChanges.active_queries.isPositive ? <TrendingUp size={14} /> : <TrendingDown size={14} />}
              <span>{data.additionalChanges.active_queries.isPositive ? '+' : '-'}{data.additionalChanges.active_queries.change}%</span>
            </div>
          </div>
          <div className="additional-metric-card">
            <div className="additional-metric-label">{t('teamMembers', language)}</div>
            <div className="additional-metric-value">{data.additionalStats.team_members.toLocaleString()}</div>
            <div className={`additional-metric-change ${data.additionalChanges.team_members.isPositive ? 'positive' : 'negative'}`}>
              {data.additionalChanges.team_members.isPositive ? <TrendingUp size={14} /> : <TrendingDown size={14} />}
              <span>{data.additionalChanges.team_members.isPositive ? '+' : ''}{data.additionalChanges.team_members.change > 0 ? data.additionalChanges.team_members.change : data.additionalChanges.team_members.change}</span>
            </div>
          </div>
        </div>
      </motion.div>

      {charts && charts.success && (
        <motion.div
          className="charts-section"
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.4 }}
        >
          <h3 className="section-title">{t('analyticsData', language)}</h3>
          <div className="charts-grid">
            <div className="chart-card">
              <h4>{t('qualityAnalysisTitle', language)}</h4>
              <p className="chart-desc">{t('qualityAnalysisSubtitle', language)}</p>
              <div className="chart-wrapper">
                <canvas ref={chartRefs.quality}></canvas>
              </div>
            </div>

            <div className="chart-card">
              <h4>{t('correlationAnalysisTitle', language)}</h4>
              <p className="chart-desc">{t('correlationAnalysisSubtitle', language)}</p>
              <div className="chart-wrapper">
                <canvas ref={chartRefs.correlation}></canvas>
              </div>
            </div>

            <div className="chart-card">
              <h4>{t('studentsTitle', language)}</h4>
              <p className="chart-desc">{t('studentsSubtitle', language)}</p>
              <div className="chart-wrapper">
                <canvas ref={chartRefs.students}></canvas>
              </div>
            </div>

            <div className="chart-card">
              <h4>{t('validationTitle', language)}</h4>
              <p className="chart-desc">{t('validationSubtitle', language)}</p>
              <div className="chart-wrapper">
                <canvas ref={chartRefs.validation}></canvas>
              </div>
            </div>

            <div className="chart-card">
              <h4>{t('semanticTitle', language)}</h4>
              <p className="chart-desc">{t('semanticSubtitle', language)}</p>
              <div className="chart-wrapper">
                <canvas ref={chartRefs.semantic}></canvas>
              </div>
            </div>

            <div className="chart-card">
              <h4>{t('criteriaTitle', language)}</h4>
              <p className="chart-desc">{t('criteriaSubtitle', language)}</p>
              <div className="chart-wrapper">
                <canvas ref={chartRefs.criteria}></canvas>
              </div>
            </div>

            <div className="chart-card">
              <h4>{t('answersTitle', language)}</h4>
              <p className="chart-desc">{t('answersSubtitle', language)}</p>
              <div className="chart-wrapper">
                <canvas ref={chartRefs.answers}></canvas>
              </div>
            </div>
          </div>
        </motion.div>
      )}

      <motion.div
        className="guide-section"
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.5 }}
      >
        <h3 className="section-title">{t('quickGuide', language)}</h3>
        <div className="guide-grid">
          <div className="guide-card">
            <div className="guide-icon" style={{ background: 'linear-gradient(135deg, #3b82f6, #0ea5e9)', boxShadow: '0 8px 20px rgba(59,130,246,0.35)' }}>
              <Database size={22} />
            </div>
            <h4>{t('importSyllabus', language)}</h4>
            <p>{t('importSyllabusDesc', language)}</p>
          </div>
          <div className="guide-card">
            <div className="guide-icon" style={{ background: 'linear-gradient(135deg, #8b5cf6, #6366f1)', boxShadow: '0 8px 20px rgba(139,92,246,0.35)' }}>
              <Cpu size={22} />
            </div>
            <h4>{t('importResults', language)}</h4>
            <p>{t('importResultsDesc', language)}</p>
          </div>
          <div className="guide-card">
            <div className="guide-icon" style={{ background: 'linear-gradient(135deg, #10b981, #059669)', boxShadow: '0 8px 20px rgba(16,185,129,0.35)' }}>
              <SlidersHorizontal size={22} />
            </div>
            <h4>{t('tableSorting', language)}</h4>
            <p>{t('tableSortingDesc', language)}</p>
          </div>
          <div className="guide-card">
            <div className="guide-icon" style={{ background: 'linear-gradient(135deg, #f59e0b, #ef4444)', boxShadow: '0 8px 20px rgba(245,158,11,0.35)' }}>
              <Gauge size={22} />
            </div>
            <h4>{t('qualityAnalysis', language)}</h4>
            <p>{t('qualityAnalysisDesc', language)}</p>
          </div>
          <div className="guide-card">
            <div className="guide-icon" style={{ background: 'linear-gradient(135deg, #6366f1, #a855f7)', boxShadow: '0 8px 20px rgba(99,102,241,0.35)' }}>
              <Network size={22} />
            </div>
            <h4>{t('correlationAnalysis', language)}</h4>
            <p>{t('correlationAnalysisDesc', language)}</p>
          </div>
          <div className="guide-card">
            <div className="guide-icon" style={{ background: 'linear-gradient(135deg, #06b6d4, #0ea5e9)', boxShadow: '0 8px 20px rgba(6,182,212,0.35)' }}>
              <Scan size={22} />
            </div>
            <h4>{t('studentAnalysis', language)}</h4>
            <p>{t('studentAnalysisDesc', language)}</p>
          </div>
        </div>
      </motion.div>
    </div>
  )
}

export default Dashboard

