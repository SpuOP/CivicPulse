// CivicPulse Platform - Admin JavaScript

class AdminDashboard {
  constructor() {
    this.init();
  }

  init() {
    this.checkAdminAuth();
    this.loadDashboardData();
    this.setupCharts();
  }

  async checkAdminAuth() {
    try {
      const response = await fetch('/untitled_folder/api/auth/status.php');
      const data = await response.json();
      
      if (!data.success || !data.user || !data.user.is_admin) {
        // Not admin, redirect to home
         window.location.href = '../index.html'; 
        return;
      }
    } catch (error) {
      console.error('Admin auth check error:', error);
       window.location.href = '../index.html'; 
    }
  }

  async loadDashboardData() {
    try {
      // Load statistics
      await this.loadStatistics();
      
      // Load moderation data
      await this.loadModerationData();
      
      // Load analytics
      await this.loadAnalytics();
      
    } catch (error) {
      console.error('Error loading dashboard data:', error);
    }
  }

  async loadStatistics() {
    try {
      const response = await fetch('/untitled_folder/api/admin/stats.php');
      const data = await response.json();
      
      if (data.success) {
        this.updateStatistics(data.stats);
      }
    } catch (error) {
      console.error('Error loading statistics:', error);
    }
  }

  updateStatistics(stats) {
    // Update stat numbers
    document.getElementById('totalUsers').textContent = this.formatNumber(stats.totalUsers);
    document.getElementById('activeUsers').textContent = this.formatNumber(stats.activeUsers);
    document.getElementById('totalIssues').textContent = this.formatNumber(stats.totalIssues);
    document.getElementById('totalVotes').textContent = this.formatNumber(stats.totalVotes);
    document.getElementById('flaggedContent').textContent = this.formatNumber(stats.flaggedContent);
    document.getElementById('openIssues').textContent = this.formatNumber(stats.openIssues);
    
    // Update growth indicators
    this.updateGrowthIndicator('usersGrowth', stats.usersGrowth);
    this.updateGrowthIndicator('issuesGrowth', stats.issuesGrowth);
    this.updateGrowthIndicator('votesGrowth', stats.votesGrowth);
    
    // Update active percentage
    const activePercentage = stats.totalUsers > 0 ? Math.round((stats.activeUsers / stats.totalUsers) * 100) : 0;
    document.getElementById('activePercentage').textContent = activePercentage;
  }

  updateGrowthIndicator(elementId, growth) {
    const element = document.getElementById(elementId);
    const textElement = document.getElementById(elementId + 'Text');
    const iconElement = element.querySelector('i');
    
    if (growth > 0) {
      element.className = 'stat-growth positive';
      iconElement.className = 'fas fa-arrow-up';
      textElement.textContent = `+${growth} this week`;
    } else if (growth < 0) {
      element.className = 'stat-growth negative';
      iconElement.className = 'fas fa-arrow-down';
      textElement.textContent = `${growth} this week`;
    } else {
      element.className = 'stat-growth';
      iconElement.className = 'fas fa-minus';
      textElement.textContent = 'No change';
    }
  }

  async loadModerationData() {
    try {
      const response = await fetch('/untitled_folder/api/admin/moderation.php');
      const data = await response.json();
      
      if (data.success) {
        this.renderRecentIssues(data.recentIssues);
        this.renderRecentComments(data.recentComments);
      }
    } catch (error) {
      console.error('Error loading moderation data:', error);
    }
  }

  renderRecentIssues(issues) {
    const container = document.getElementById('recentIssues');
    
    if (issues.length === 0) {
      container.innerHTML = '<p class="text-muted">No recent issues</p>';
      return;
    }
    
    container.innerHTML = issues.map(issue => `
      <div class="moderation-item">
        <div class="item-content">
          <div class="item-title">${this.escapeHtml(issue.title)}</div>
          <div class="item-meta">
            <span>by ${this.escapeHtml(issue.author_name)}</span>
            <span>${this.formatDate(issue.created_at)}</span>
          </div>
        </div>
        <div class="item-actions">
          <button class="btn btn-sm btn-secondary" onclick="window.open('../issues/view.html?id=${issue.id}', '_blank')">
            <i class="fas fa-eye"></i>
          </button>
        </div>
      </div>
    `).join('');
  }

  renderRecentComments(comments) {
    const container = document.getElementById('recentComments');
    
    if (comments.length === 0) {
      container.innerHTML = '<p class="text-muted">No recent comments</p>';
      return;
    }
    
    container.innerHTML = comments.map(comment => `
      <div class="moderation-item">
        <div class="item-content">
          <div class="item-title">${this.escapeHtml(comment.content.substring(0, 100))}${comment.content.length > 100 ? '...' : ''}</div>
          <div class="item-meta">
            <span>by ${this.escapeHtml(comment.author_name)}</span>
            <span>${this.formatDate(comment.created_at)}</span>
          </div>
        </div>
        <div class="item-actions">
          <button class="btn btn-sm btn-secondary" onclick="window.open('../issues/view.html?id=${comment.issue_id}', '_blank')">
            <i class="fas fa-eye"></i>
          </button>
        </div>
      </div>
    `).join('');
  }

  async loadAnalytics() {
    try {
      const response = await fetch('/untitled_folder/api/admin/analytics.php');
      const data = await response.json();
      
      if (data.success) {
        this.renderTrendingIssues(data.trendingIssues);
        this.renderUserActivity(data.userActivity);
      }
    } catch (error) {
      console.error('Error loading analytics:', error);
    }
  }

  renderTrendingIssues(issues) {
    const container = document.getElementById('trendingIssues');
    
    if (issues.length === 0) {
      container.innerHTML = '<p class="text-muted">No trending issues</p>';
      return;
    }
    
    container.innerHTML = issues.map(issue => `
      <div class="trending-item">
        <div class="trending-title">${this.escapeHtml(issue.title)}</div>
        <div class="trending-votes">
          <i class="fas fa-chevron-up text-success"></i>
          ${issue.vote_count} votes
        </div>
      </div>
    `).join('');
  }

  renderUserActivity(activity) {
    const container = document.getElementById('userActivity');
    
    container.innerHTML = `
      <div class="analytics-item">
        <div class="analytics-label">Most Active User</div>
        <div class="analytics-value">${this.escapeHtml(activity.mostActiveUser || 'N/A')}</div>
      </div>
      <div class="analytics-item">
        <div class="analytics-label">Average Issues per User</div>
        <div class="analytics-value">${activity.avgIssuesPerUser || 0}</div>
      </div>
      <div class="analytics-item">
        <div class="analytics-label">Average Comments per Issue</div>
        <div class="analytics-value">${activity.avgCommentsPerIssue || 0}</div>
      </div>
    `;
  }

  setupCharts() {
    // User Growth Chart
    const userCtx = document.getElementById('userGrowthChart');
    if (userCtx) {
      new Chart(userCtx, {
        type: 'line',
        data: {
          labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
          datasets: [{
            label: 'Users',
            data: [12, 19, 3, 5, 2, 3],
            borderColor: 'rgb(124, 58, 237)',
            backgroundColor: 'rgba(124, 58, 237, 0.1)',
            tension: 0.1
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: {
              display: false
            }
          },
          scales: {
            y: {
              beginAtZero: true
            }
          }
        }
      });
    }

    // Issue Activity Chart
    const issueCtx = document.getElementById('issueActivityChart');
    if (issueCtx) {
      new Chart(issueCtx, {
        type: 'bar',
        data: {
          labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
          datasets: [{
            label: 'Issues',
            data: [12, 19, 3, 5, 2, 3],
            backgroundColor: 'rgba(124, 58, 237, 0.8)'
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: {
              display: false
            }
          },
          scales: {
            y: {
              beginAtZero: true
            }
          }
        }
      });
    }
  }

  formatNumber(num) {
    if (num >= 1000000) {
      return (num / 1000000).toFixed(1) + 'M';
    } else if (num >= 1000) {
      return (num / 1000).toFixed(1) + 'K';
    }
    return num.toString();
  }

  formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffTime = Math.abs(now - date);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays === 1) return 'Today';
    if (diffDays === 2) return 'Yesterday';
    if (diffDays < 7) return `${diffDays - 1} days ago`;
    if (diffDays < 30) return `${Math.floor(diffDays / 7)} weeks ago`;
    if (diffDays < 365) return `${Math.floor(diffDays / 30)} months ago`;
    return `${Math.floor(diffDays / 365)} years ago`;
  }

  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
}

// Add admin-specific CSS
const adminStyles = document.createElement('style');
adminStyles.textContent = `
  .chart-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: var(--space-6);
    margin-top: var(--space-6);
  }
  
  .chart-card {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: var(--space-6);
    box-shadow: var(--shadow-md);
  }
  
  .chart-card h3 {
    color: var(--text-primary);
    margin-bottom: var(--space-4);
  }
  
  .chart-container {
    position: relative;
    height: 300px;
  }
  
  @media (max-width: 768px) {
    .chart-grid {
      grid-template-columns: 1fr;
    }
    
    .chart-container {
      height: 250px;
    }
  }
`;
document.head.appendChild(adminStyles);

// Initialize admin dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  window.adminDashboard = new AdminDashboard();
});
