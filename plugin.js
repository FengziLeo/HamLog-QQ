/**
 * 业余无线电呼号记录插件
 * 监听用户消息中的业余无线电呼号，记录到数据库中
 */

const { Context, Schema } = require('koishi')

// 插件名称
const name = 'bf1-servermanagertools'

/**
 * 插件配置接口
 */
const Config = Schema.object({
  // 呼号记录保存时间（分钟）
  recordTimeout: Schema.number().default(1).description('呼号记录超时时间（分钟）'),
  // 是否启用自动撤回功能
  autoRecall: Schema.boolean().default(true).description('是否自动撤回上一条消息'),
})

/**
 * 业余无线电呼号正则表达式
 * 匹配格式：1-2位字母 + 1-4位数字 + 1-3位字母
 * 例如：BG7ABC, BH1ABC, BY1ABC 等
 */
const CALLSIGN_REGEX = /\b[A-Z]{1,2}\d{1,4}[A-Z]{1,3}\b/gi

/**
 * 提取呼号信息
 * @param {string} message - 消息内容
 * @returns {Array} 呼号列表
 */
function extractCallsigns(message) {
  const matches = message.match(CALLSIGN_REGEX)
  return matches ? [...new Set(matches)] : [] // 去重
}

/**
 * 提取其他信息
 * @param {string} message - 消息内容
 * @returns {Object} 空对象
 */
function extractAdditionalInfo(message) {
  return {}
}

/**
 * 插件主函数
 */
function apply(ctx, config) {
  // 插件初始化
  const logger = ctx.logger('callsign-recorder')
  
  // 硬编码API配置
  const apiConfig = {
    serverUrl: 'https://cq.fz.do/uv-qso/index.php',
    apiKey: '7x9A2z5B1y8C3w6D4v7E',
    ...config // 保留其他配置项
  }
  
  logger.info('业余无线电呼号记录插件已加载')
  logger.info('插件配置:', apiConfig)
  
  // 用户记录缓存，用于跟踪一分钟内的消息
  const userRecords = new Map()
  
  // 清理过期记录的定时任务
  const cleanupInterval = setInterval(() => {
    const now = Date.now()
    for (const [userId, record] of userRecords.entries()) {
      if (now - record.timestamp > config.recordTimeout * 60 * 1000) {
        userRecords.delete(userId)
      }
    }
  }, 30000) // 每30秒清理一次
  
  // 监听消息事件
  ctx.on('message', async (session) => {
    const message = session.content
    const userId = session.userId
    const now = Date.now()
    ctx.logger('callsign-recorder').info(`收到消息 from ${session.username} (${userId}): ${message}`)
    ctx.logger('callsign-recorder').debug(`从消息 "${message}" 中提取呼号结果:`, extractCallsigns(message))
    
    // 提取呼号
    const callsigns = extractCallsigns(message)
    
    if (callsigns.length === 0) {
      logger.info('消息中未检测到呼号')
      return // 没有呼号，直接返回
    }
    logger.info(`检测到呼号: ${callsigns.join(', ')}`)
    
    // 提取其他信息
    const additionalInfo = extractAdditionalInfo(message)
    
    // 为每个呼号单独处理
    for (const callsign of callsigns) {
      // 获取用户现有记录
      let userRecord = userRecords.get(`${userId}_${callsign}`)
      
      if (!userRecord) {
        // 创建新记录
        userRecord = {
          userId,
          callsigns: [callsign],
          additionalInfo,
          timestamp: now,
          messageId: null,
          isComplete: false
        }
        userRecords.set(`${userId}_${callsign}`, userRecord)
      } else {
        // 检查是否在时间窗口内
        if (now - userRecord.timestamp <= config.recordTimeout * 60 * 1000) {
          // 合并其他信息
          userRecord.additionalInfo = { ...userRecord.additionalInfo, ...additionalInfo }
          userRecord.timestamp = now
        } else {
          // 超时，创建新记录
          userRecord = {
            userId,
            callsigns: [callsign],
            additionalInfo,
            timestamp: now,
            messageId: null,
            isComplete: false
          }
          userRecords.set(`${userId}_${callsign}`, userRecord)
        }
      }
      
      // 判断是否为完整记录
      const hasAdditionalInfo = false
      userRecord.isComplete = true
      
      try {
        logger.info(`开始处理呼号 ${callsign} 的记录`)
        
        // 先查询历史记录
        let historyMessage = ''
        try {
          const historyResponse = await ctx.http.get(`${apiConfig.serverUrl}/callsign/query`, {
            params: {
              userId: userId,
              callsign: callsign,
              limit: 100 // 查询足够多的记录以统计次数
            },
            headers: {
              'X-API-Key': apiConfig.apiKey
            }
          })
          
          if (historyResponse && Array.isArray(historyResponse)) {
            if (historyResponse.length > 0) {
              const lastTime = new Date(historyResponse[0].timestamp).toLocaleString()
              historyMessage = `\n\n📅 已通联 ${historyResponse.length} 次，上次时间：${lastTime}`
            }
          }
        } catch (error) {
          logger.warn('查询历史记录失败:', error)
        }
        
        // 保存到数据库
        const recordData = {
          userId,
          username: session.username,
          callsigns: callsign,
          device: null,
          antenna: null,
          power: null,
          location: null,
          timestamp: new Date(userRecord.timestamp),
          messageContent: message,
          isComplete: true
        }
        
        // 调用PHP API保存或更新记录
        try {
          // 检查是否为补充信息
          const isUpdate = userRecord.messageId !== null && hasAdditionalInfo
          
          const response = isUpdate 
            ? await ctx.http.put(`${apiConfig.serverUrl}/callsign/record`, recordData, {
                headers: {
                  'X-API-Key': apiConfig.apiKey,
                  'Content-Type': 'application/json'
                }
              })
            : await ctx.http.post(`${apiConfig.serverUrl}/callsign/record`, recordData, {
                headers: {
                  'X-API-Key': apiConfig.apiKey,
                  'Content-Type': 'application/json'
                }
              })
          
          logger.info(isUpdate ? `呼号 ${callsign} 记录已更新:` : `呼号 ${callsign} 记录已保存:`, response)
        } catch (error) {
            throw new Error(`API请求失败: ${error.message}`)
        }
        
        // 构建回复消息
        const replyMessage = `✅ 呼号 ${callsign} 记录成功！${historyMessage}`
        
        // 如果有上一条消息且配置了自动撤回，并且当前是补充信息
        if (userRecord.messageId && config.autoRecall && hasAdditionalInfo) {
          try {
            await session.bot.recallMessage(session.channelId, userRecord.messageId)
          } catch (error) {
            ctx.logger('callsign-recorder').warn('撤回消息失败:', error.message)
          }
        }
        
        // 发送回复消息
        const sentMessage = await session.send(replyMessage)
        userRecord.messageId = sentMessage.messageId
        
        // 记录日志
        ctx.logger('callsign-recorder').info(`用户 ${session.username} 记录呼号: ${callsign}`)
        
      } catch (error) {
        logger.error(`保存呼号 ${callsign} 记录失败:`, error)
        await session.send(`❌ 呼号 ${callsign} 记录失败，请稍后重试`)
        logger.info('错误详情:', error.stack)
      }
    }
    return;
 })
  
  // 插件卸载时的清理工作
  ctx.on('dispose', () => {
    clearInterval(cleanupInterval)
    userRecords.clear()
    logger.info('业余无线电呼号记录插件正在卸载...')
    logger.info('清理了用户记录缓存和定时器')
  })
}

// 导出插件
module.exports = {
  name,
  Config,
  apply,
}
