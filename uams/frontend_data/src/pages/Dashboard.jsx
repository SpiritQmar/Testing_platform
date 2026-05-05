import React, { useState, useEffect, useRef } from 'react'
import { motion } from 'framer-motion'
import { TrendingUp, TrendingDown, FileQuestion, Users, ClipboardList, Award } from 'lucide-react'
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
      fetch('/diplom_project/UAMS/ai_analytics/api/overview.php').then(res => res.json()),
      fetch('/diplom_project/UAMS/ai_analytics/api/charts.php').then(res => res.json())
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

      const options = {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: {
            position: 'bottom',
            display: type === 'doughnut' || type === 'pie'
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                const label = context.label || ''
                let value = 0

                if (type === 'doughnut' || type === 'pie') {
                  value = context.parsed
                } else if (type === 'bar' || type === 'line') {
                  value = context.parsed.y
                } else if (type === 'radar') {
                  value = context.parsed.r
                }

                return label + ': ' + value
              }
            }
          }
        }
      }

      if (type === 'bar' || type === 'line') {
        options.scales = {
          y: {
            beginAtZero: true
          }
        }
        if (type === 'bar' && data.labels.length <= 2) {
          options.indexAxis = 'y'
          options.scales = {
            x: {
              beginAtZero: true
            }
          }
        }
      }

      if (type === 'line') {
        data.datasets[0].fill = true
        data.datasets[0].tension = 0.4
        data.datasets[0].borderColor = data.datasets[0].backgroundColor[0]
        data.datasets[0].backgroundColor = data.datasets[0].backgroundColor[0] + '40'
      }

      return new window.Chart(ctx, {
        type: type,
        data: data,
        options: options
      })
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
      chartInstances.current.semantic = createChart(chartRefs.semantic, charts.semantic, 'bar')
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

      <motion.div
        className="guide-section"
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.4 }}
      >
        <h3 className="section-title">{t('quickGuide', language)}</h3>
        <div className="guide-grid">
          <div className="guide-card">
            <div className="guide-icon">📥</div>
            <h4>{t('importSyllabus', language)}</h4>
            <p>{t('importSyllabusDesc', language)}</p>
          </div>
          <div className="guide-card">
            <div className="guide-icon">📊</div>
            <h4>{t('importResults', language)}</h4>
            <p>{t('importResultsDesc', language)}</p>
          </div>
          <div className="guide-card">
            <div className="guide-icon">🔄</div>
            <h4>{t('tableSorting', language)}</h4>
            <p>{t('tableSortingDesc', language)}</p>
          </div>
          <div className="guide-card">
            <div className="guide-icon">📈</div>
            <h4>{t('qualityAnalysis', language)}</h4>
            <p>{t('qualityAnalysisDesc', language)}</p>
          </div>
          <div className="guide-card">
            <div className="guide-icon">🔗</div>
            <h4>{t('correlationAnalysis', language)}</h4>
            <p>{t('correlationAnalysisDesc', language)}</p>
          </div>
          <div className="guide-card">
            <div className="guide-icon">👥</div>
            <h4>{t('studentAnalysis', language)}</h4>
            <p>{t('studentAnalysisDesc', language)}</p>
          </div>
        </div>
      </motion.div>

      {charts && charts.success && (
        <motion.div
          className="charts-section"
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.5 }}
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
    </div>
  )
}

export default Dashboard

