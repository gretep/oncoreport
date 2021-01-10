/* eslint-disable react/no-danger */
import React, { createRef, useEffect, useState } from 'react';
import Typography from '@material-ui/core/Typography';
import Paper from '@material-ui/core/Paper';
import Box from '@material-ui/core/Box';
import Grid from '@material-ui/core/Grid';
import CircularProgress from '@material-ui/core/CircularProgress';
import { Formik, Form } from 'formik';
import * as Yup from 'yup';
import Backdrop from '@material-ui/core/Backdrop';
import { api, activeWindow } from 'electron-util';
import {
  Button,
  Collapse,
  createStyles,
  FormGroup,
  Link,
  makeStyles,
} from '@material-ui/core';
import Convert from 'ansi-to-html';
import LinearProgress from '@material-ui/core/LinearProgress';
import { DependencyContainer } from 'tsyringe';
import { useContainer, useService } from '../../../reactInjector';
import { DockerManager, Settings, ValidateConfig } from '../../../api';
import { runAsync } from '../utils';
import { SubmitButton } from '../ui/Button';
import { ConfigObjectType } from '../../../interfaces';
import Wizard from '../ui/Wizard';
import SelectField from '../ui/Form/SelectField';
import TextField from '../ui/Form/TextField';
import SwitchField from '../ui/Form/SwitchField';
import FileField from '../ui/Form/FileField';

const COSMIC_URL = 'https://cancer.sanger.ac.uk/cosmic/register';
const DEFAULT_START_PORT = 18080;

const useStyles = makeStyles((theme) =>
  createStyles({
    root: {
      padding: theme.spacing(3, 2),
    },
    formControl: {
      margin: theme.spacing(1),
      minWidth: 120,
    },
    backButton: {
      marginRight: theme.spacing(1),
    },
    instructions: {
      marginTop: theme.spacing(1),
      marginBottom: theme.spacing(1),
      fontSize: theme.typography.fontSize,
    },
    instructionsSmall: {
      margin: theme.spacing(1),
      fontSize: '0.8rem',
    },
    backdrop: {
      zIndex: theme.zIndex.drawer + 1,
      color: '#fff',
    },
    bold: {
      fontWeight: theme.typography.fontWeightBold,
    },
    logContainer: {
      background: 'black',
    },
  })
);

const convert = new Convert({ newline: true });

function logToHtml(log: string): string {
  return convert.toHtml(
    log
      .split('\n')
      .map((s) => s.split('\r').pop())
      .join('\n')
  );
}

interface ExtendedConfig extends ConfigObjectType {
  cosmicUsername: string;
  cosmicPassword: string;
}

type Props = { values: ExtendedConfig };

function CosmicForm({ label }: { label?: boolean }) {
  const classes = useStyles();
  return (
    <>
      <Grid container justify="space-evenly" alignItems="center" spacing={2}>
        {label && (
          <Grid item md={2} className={classes.bold}>
            COSMIC Account:
          </Grid>
        )}
        <Grid item md>
          <TextField label="Username" name="cosmicUsername" required />
        </Grid>
        <Grid item md>
          <TextField
            label="Password"
            name="cosmicPassword"
            type="password"
            required
          />
        </Grid>
      </Grid>
    </>
  );
}
CosmicForm.defaultProps = { label: false };

function Step0({ values }: Props) {
  const classes = useStyles();
  return (
    <>
      <Typography className={classes.instructions}>
        Here you can select whether you wish to use OncoReport with a local
        docker installation or with a remote server.
      </Typography>
      <SwitchField label="Use a local docker installation?" name="local" />
      <Collapse in={values.local}>
        <FileField
          label="Local storage path"
          name="dataPath"
          dialogOptions={{ properties: ['openDirectory'] }}
          helperText="Path where all data files will be stored"
        />
        <TextField label="Local container name" name="containerName" />
        <FileField
          label="Local docker socket"
          name="socketPath"
          dialogOptions={{ properties: ['openFile'], filters: [] }}
          helperText="The default value is automatically detected. Change only if you think this is wrong."
        />
      </Collapse>
    </>
  );
}

function Step1({ values }: Props) {
  const classes = useStyles();
  const { local } = values;
  return (
    <>
      <Typography className={classes.instructions}>
        Here you can configure the connection with OncoReport
        {local
          ? ' local docker container and your COSMIC account.'
          : ' remote server.'}
      </Typography>
      <Grid container justify="space-around" alignItems="center" spacing={3}>
        <Grid item xs>
          <SelectField
            label="API Protocol"
            name="apiProtocol"
            options={{ http: 'http', https: 'https' }}
            required
          />
        </Grid>
        <Grid item xs>
          <TextField label="API Hostname" name="apiHostname" required />
        </Grid>
        <Grid item xs>
          <TextField label="API Port" name="apiPort" type="number" required />
        </Grid>
      </Grid>
      <Grid container justify="space-around" alignItems="center" spacing={3}>
        <Grid item md>
          <TextField label="API Path" name="apiPath" required />
        </Grid>
        <Grid item md>
          <TextField label="Public Path" name="publicPath" required />
        </Grid>
      </Grid>
      {!local && <TextField label="API key" name="apiKey" />}
      {local && <CosmicForm label />}
    </>
  );
}

function Step2() {
  const classes = useStyles();
  return (
    <>
      <Typography className={classes.instructions}>
        All parameters have been set. Click &quot;Install&quot; to start the
        process.
      </Typography>
    </>
  );
}

function configFromExtendedConfig(c: ExtendedConfig): ConfigObjectType {
  return {
    apiHostname: c.apiHostname,
    apiKey: c.apiKey,
    apiPath: c.apiPath,
    apiPort: c.apiPort,
    apiProtocol: c.apiProtocol,
    autoStopDockerOnClose: false,
    configured: true,
    containerName: c.containerName,
    dataPath: c.dataPath,
    local: c.local,
    publicPath: c.publicPath,
    socketPath: c.socketPath,
  };
}

async function runSetup(
  values: ExtendedConfig,
  setLog: React.Dispatch<React.SetStateAction<string>>,
  settings: Settings,
  container: DependencyContainer
) {
  const { cosmicUsername, cosmicPassword, local } = values;
  let newConfig = configFromExtendedConfig(values);
  let log = '';
  if (local) {
    const manager = container.resolve(DockerManager);
    manager.config = newConfig;
    if (!(await manager.hasImage())) {
      log += 'Container image not found...Downloading...\n';
      setLog(log);
      const state = await manager.pullImage((s) => {
        setLog(`${log}${s.toString()}`);
      });
      log += `${state.toString()}\n`;
    }
  }
  log += 'Validating configuration:\n';
  setLog(log);
  const validator = container.resolve(ValidateConfig);
  validator.newConfig = newConfig;
  newConfig = await validator.validate((m) => {
    log += m;
    setLog(log);
  });
  log += 'Saving configuration...';
  setLog(log);
  // @TODO
}

export default function SetupWizard() {
  const classes = useStyles();
  const settings = useService(Settings);
  const container = useContainer();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [firstStep, setFirstStep] = useState(true);
  const [advancedSetup, setAdvancedSetup] = useState(false);
  const [logContent, setLogContent] = useState('');
  const [freePort, setFreePort] = useState(DEFAULT_START_PORT);
  const logRef = createRef<HTMLDivElement>();

  useEffect(() => {
    runAsync(async () => {
      const port = await settings.findFreePort(DEFAULT_START_PORT);
      setFreePort(port);
      setLoading(false);
    });
  }, [settings]);

  const steps = [
    'Docker parameters',
    'Connection parameters',
    'Complete setup',
  ];

  const MySubmit = () => <SubmitButton isSaving={saving} text="Install" />;

  const validationSchema = Yup.object().shape({
    local: Yup.boolean(),
    dataPath: Yup.string().when('local', {
      is: true,
      then: Yup.string().required(),
      otherwise: Yup.string().notRequired(),
    }),
    socketPath: Yup.string().notRequired(),
    containerName: Yup.string().when('local', {
      is: true,
      then: Yup.string().required(),
      otherwise: Yup.string().notRequired(),
    }),
    apiProtocol: Yup.string().required(),
    apiHostname: Yup.string().required(),
    apiPort: Yup.number().positive().integer().min(1).max(65535),
    apiPath: Yup.string().required(),
    publicPath: Yup.string().required(),
    apiKey: Yup.string().when('local', {
      is: true,
      then: Yup.string().notRequired(),
      otherwise: Yup.string().required(),
    }),
    cosmicUsername: Yup.string().when('local', {
      is: true,
      then: Yup.string().required(),
      otherwise: Yup.string().notRequired(),
    }),
    cosmicPassword: Yup.string().when('local', {
      is: true,
      then: Yup.string().required(),
      otherwise: Yup.string().notRequired(),
    }),
  });

  return (
    <>
      <Box>
        <Paper className={classes.root}>
          {!saving ? (
            <>
              <Typography variant="h5" component="h3">
                Setup Wizard
              </Typography>
              <Formik<ExtendedConfig>
                initialValues={{
                  ...settings.getDefaultConfig(freePort),
                  cosmicUsername: '',
                  cosmicPassword: '',
                }}
                onSubmit={async (v) => {
                  setSaving(true);
                  return runSetup(v, setLogContent, settings, container);
                }}
                validationSchema={validationSchema}
              >
                {({ values }) => (
                  <Form>
                    {firstStep && (
                      <>
                        <Typography className={classes.instructions}>
                          Here you will setup your OncoReport instance through a
                          step-by-step procedure. Before you proceed with the
                          process, you should prepare your COSMIC account.
                          COSMIC is the world&apos;s largest and most
                          comprehensive resource for exploring the impact of
                          somatic mutations in human cancer. It is a fundamental
                          part of Oncoreport annotation procedure. If you do not
                          have an account, you can create one by{' '}
                          <Link
                            href={COSMIC_URL}
                            onClick={(
                              e: React.MouseEvent<HTMLAnchorElement>
                            ) => {
                              e.preventDefault();
                              // eslint-disable-next-line @typescript-eslint/ban-ts-comment
                              // @ts-ignore
                              api.shell.openExternal(e.target.href);
                            }}
                          >
                            clicking here
                          </Link>
                          .
                        </Typography>
                        <Grid
                          container
                          spacing={1}
                          direction="column"
                          alignItems="center"
                        >
                          <Grid item>
                            <Typography className={classes.bold}>
                              Choose setup mode
                            </Typography>
                          </Grid>
                          <Grid item>
                            <Button
                              type="button"
                              variant="contained"
                              color="primary"
                              onClick={(e) => {
                                e.preventDefault();
                                setAdvancedSetup(false);
                                setFirstStep(false);
                              }}
                              disabled={saving}
                            >
                              Express setup
                            </Button>
                          </Grid>
                          <Grid item>
                            <Button
                              type="button"
                              variant="contained"
                              color="primary"
                              onClick={(e) => {
                                e.preventDefault();
                                setAdvancedSetup(true);
                                setFirstStep(false);
                              }}
                              disabled={saving}
                            >
                              Custom setup
                            </Button>
                          </Grid>
                        </Grid>
                      </>
                    )}
                    {!firstStep && advancedSetup && (
                      <Wizard
                        steps={steps}
                        submitButton={MySubmit}
                        connectedFields={[
                          ['local', 'dataPath', 'socketPath', 'containerName'],
                          [
                            'apiProtocol',
                            'apiHostname',
                            'apiPort',
                            'apiPath',
                            'publicPath',
                            'apiKey',
                            'cosmicUsername',
                            'cosmicPassword',
                          ],
                          [],
                        ]}
                      >
                        <Step0 values={values} />
                        <Step1 values={values} />
                        <Step2 />
                      </Wizard>
                    )}
                    {!firstStep && !advancedSetup && (
                      <>
                        <Typography className={classes.instructions}>
                          Please, insert here your COSMIC credentials (username
                          and password), and click &quot;Install&quot; to start
                          the process. If you do not have a COSMIC account, you
                          can create one by{' '}
                          <Link
                            href={COSMIC_URL}
                            onClick={(
                              e: React.MouseEvent<HTMLAnchorElement>
                            ) => {
                              e.preventDefault();
                              // eslint-disable-next-line @typescript-eslint/ban-ts-comment
                              // @ts-ignore
                              api.shell.openExternal(e.target.href);
                            }}
                          >
                            clicking here
                          </Link>
                          .
                        </Typography>
                        <Grid
                          container
                          spacing={1}
                          direction="column"
                          alignItems="center"
                        >
                          <Grid item>
                            <CosmicForm />
                          </Grid>
                          <Grid item>{MySubmit()}</Grid>
                        </Grid>
                      </>
                    )}
                  </Form>
                )}
              </Formik>
            </>
          ) : (
            <>
              <Typography variant="h5" component="h3">
                Installing...
              </Typography>
              <LinearProgress />
              <div
                className={classes.logContainer}
                dangerouslySetInnerHTML={{ __html: logToHtml(logContent) }}
              />
              <div ref={logRef} />
            </>
          )}
        </Paper>
      </Box>
      <Backdrop className={classes.backdrop} open={loading}>
        <CircularProgress color="inherit" />
      </Backdrop>
    </>
  );
}
