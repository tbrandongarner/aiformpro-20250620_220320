const fs = require('fs').promises;
const { constants: fsConstants } = require('fs');
const path = require('path');

async function addMissingPlanFile() {
  const configPath = path.resolve(__dirname, '../src/config/plans.json');
  const plansDir = path.resolve(__dirname, '../src/plans');

  try {
    await fs.access(configPath, fsConstants.F_OK).catch(() => {
      throw new Error(`Config file not found at ${configPath}`);
    });

    const configContent = await fs.readFile(configPath, 'utf8');
    let plansConfig;
    try {
      plansConfig = JSON.parse(configContent);
    } catch {
      throw new Error(`Invalid JSON in config file ${configPath}`);
    }

    if (!Array.isArray(plansConfig)) {
      throw new Error(`Expected array of plans in ${configPath}`);
    }

    await fs.mkdir(plansDir, { recursive: true });

    const createdFiles = [];
    for (const plan of plansConfig) {
      if (!plan.slug) continue;
      const fileName = `${plan.slug}.json`;
      const filePath = path.resolve(plansDir, fileName);

      const exists = await fs
        .access(filePath, fsConstants.F_OK)
        .then(() => true)
        .catch(() => false);

      if (!exists) {
        const defaultPlan = {
          slug: plan.slug,
          name: plan.name || '',
          price: typeof plan.price === 'number' ? plan.price : 0,
          features: Array.isArray(plan.features) ? plan.features : [],
          createdAt: new Date().toISOString(),
        };
        await fs.writeFile(filePath, JSON.stringify(defaultPlan, null, 2), 'utf8');
        createdFiles.push(fileName);
      }
    }

    if (createdFiles.length) {
      console.log('Created missing plan files:', createdFiles.join(', '));
    } else {
      console.log('No missing plan files to create.');
    }
  } catch (err) {
    console.error('Error in addMissingPlanFile:', err.stack);
    process.exit(1);
  }
}

if (require.main === module) {
  addMissingPlanFile();
}

module.exports = addMissingPlanFile;